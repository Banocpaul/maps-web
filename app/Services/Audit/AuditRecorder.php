<?php

namespace App\Services\Audit;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AuditRecorder
{
    private bool $writing = false;
    private array $pendingInserts = [];

    public function flushInserts(): void
    {
        $pending = $this->pendingInserts;
        $this->pendingInserts = [];
        foreach ($pending as $entry) $this->record($entry['values']);
    }

    public function discardRolledBackInserts(int $level): void
    {
        $this->pendingInserts = array_filter($this->pendingInserts, fn ($entry) => $entry['level'] <= $level);
    }

    public function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if (preg_match('/password|token|secret|credential|api.?key|authorization|cookie/i', (string) $key)) {
                $values[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $values[$key] = $this->redact($value);
            }
        }
        return $values;
    }

    public function record(array $values): void
    {
        if ($this->writing) {
            return;
        }
        $this->writing = true;
        try {
            if (! Schema::hasTable('activity_logs')) {
                return;
            }
            $request = app()->runningInConsole() ? null : request();
            $user = $request?->user() ?? Auth::user();
            ActivityLog::create(array_replace([
                'user_id' => $user?->id,
                'user_name' => $user?->full_name ?: ($request ? 'Guest user' : 'System'),
                'role_name' => $user?->role()->value('name'),
                'route_name' => $request?->route()?->getName(),
                'http_method' => $request?->method(),
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 1000) : null,
                // Record/database events describe committed changes, not HTTP outcomes.
                'response_status' => null,
            ], $values));
        } catch (Throwable $error) {
            // Logging must not break the action; omit exception text which can include bindings.
            Log::warning('Audit entry could not be recorded.', ['error_type' => get_class($error)]);
        } finally {
            $this->writing = false;
        }
    }

    public function modelChanged(string $event, Model $model): void
    {
        if ($model instanceof ActivityLog || ! str_starts_with(get_class($model), 'App\\Models\\')) {
            return;
        }
        // The model already obtained its generated ID. It is now safe to write insert audit rows.
        $this->flushInserts();
        $changes = $model->getChanges();
        $meaningful = array_diff(array_keys($changes), ['updated_at', 'last_seen_at', 'last_login_at', 'remember_token']);
        if ($event === 'updated' && ! $meaningful) {
            return;
        }
        $old = $event === 'created' ? null : ($event === 'updated'
            ? array_intersect_key($model->getRawOriginal(), $changes) : $model->getRawOriginal());
        $new = $event === 'deleted' ? null : ($event === 'updated' ? $changes : $model->getAttributes());
        $automated = $model->getTable() === 'public_incident_reports'
            && ($changes['community_verified'] ?? false) && ! $model->validated_by;
        $values = [
            'action' => $automated ? 'auto_verify_report' : 'record_'.$event,
            'module' => str_replace('_', '-', $model->getTable()),
            'description' => ($automated ? 'System automatically verified' : ucfirst($event))
                .' '.class_basename($model).' record #'.$model->getKey().'.',
            'subject_type' => get_class($model), 'subject_id' => $model->getKey(),
            'old_values' => $old === null ? null : $this->redact($old),
            'new_values' => $new === null ? null : $this->redact($new),
        ];
        if ($automated) {
            $values += ['user_id' => null, 'user_name' => 'System', 'role_name' => 'Automatic verification'];
        }
        $this->record($values);
    }

    public function databaseWritten(QueryExecuted $query): void
    {
        if ($this->writing || ! preg_match('/^\s*(insert(?:\s+ignore)?\s+into|replace\s+into|update|delete\s+from)\s+[`"]?([a-zA-Z0-9_]+)/i', $query->sql, $match)) {
            return;
        }
        $table = strtolower($match[2]);
        if (in_array($table, ['activity_logs', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'migrations', 'password_reset_tokens'], true)) {
            return;
        }
        // Online presence updates aren't user actions. Never store SQL or bindings.
        if ($table === 'users' && preg_match('/^update\s+[`"]?users[`"]?\s+set\s+[`"]?last_seen_at[`"]?\s*=\s*\?\s+where/i', $query->sql)) {
            return;
        }
        $operation = strtolower(explode(' ', trim($match[1]))[0]);
        $values = [
            'action' => 'database_'.$operation,
            'module' => str_replace('_', '-', $table),
            'description' => 'Executed '.$operation.' on '.$table.'. See record events for field changes when available.',
            'subject_type' => $table,
            'new_values' => ['table' => $table, 'operation' => $operation],
        ];
        if (in_array($operation, ['insert', 'replace'], true)) {
            // QueryExecuted runs before insertGetId reads PDO::lastInsertId().
            // A synchronous audit INSERT would replace the business row's ID.
            $this->pendingInserts[] = ['level' => $query->connection->transactionLevel(), 'values' => $values];
        } else {
            $this->flushInserts();
            $this->record($values);
        }
    }
}
