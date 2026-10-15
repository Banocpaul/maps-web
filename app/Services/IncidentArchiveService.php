<?php
namespace App\Services;

use App\Models\{FireIncident, FloodIncidentRecord, FloodTrainingRecord, User};
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class IncidentArchiveService
{
    public const TYPES = ['fire' => 'Fire incidents', 'flood' => 'Flood incident records', 'observations' => 'Flood field observations'];
    public function definition(string $type): array {
        return match ($type) {
            'fire' => [FireIncident::class, 'fire.edit', 'status', 'Resolved', 'COALESCE(occurred_at, reported_at)', false],
            'flood' => [FloodIncidentRecord::class, 'flood.edit', 'status', 'Subsided', 'COALESCE(flood_start_datetime, observation_datetime)', true],
            'observations' => [FloodTrainingRecord::class, 'flood.edit', 'flood_status', 'Subsided', 'observed_at', false],
            default => abort(404),
        };
    }
    public function cutoff(bool $local = false): Carbon {
        $date = now('Asia/Manila')->startOfDay()->subYearsNoOverflow(3);
        return $local ? $date : $date->utc();
    }
    public function query(string $type, string $state): Builder {
        [$class, , $status, $closed, $date, $local] = $this->definition($type);
        $query = $class::query();
        if ($state === 'archived') return $query->whereNotNull('archived_at');
        return $query->whereNull('archived_at')->where($status, $closed)->whereRaw($date.' < ?', [$this->cutoff($local)->toDateTimeString()]);
    }
    public function authorize(User $user, string $type): void {
        abort_unless($user->hasPermission('records.view') && $user->hasPermission($this->definition($type)[1]), 403);
    }
    public function change(string $type, int $id, User $user, string $reason, bool $restore = false): Model {
        $this->authorize($user, $type);
        return DB::transaction(function () use ($type, $id, $user, $reason, $restore): Model {
            [$class] = $this->definition($type);
            $record = $class::lockForUpdate()->findOrFail($id);
            abort_unless($this->query($type, $restore ? 'archived' : 'eligible')->whereKey($id)->exists(), 409,
                $restore ? 'This incident is not archived.' : 'Only closed incidents older than three years can be archived.');
            $record->forceFill(['archived_at' => $restore ? null : now(),
                'archived_by' => $restore ? null : $user->id, 'archive_reason' => $restore ? null : $reason])->save();
            app(\App\Services\Audit\AuditRecorder::class)->record([
                'action' => $restore ? 'restore_incident' : 'archive_incident', 'module' => 'incident-archives',
                'subject_type' => $class, 'subject_id' => $id, 'description' => $reason,
            ]);
            return $record;
        });
    }
}
