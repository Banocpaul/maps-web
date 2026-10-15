<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;

class IncidentArchiveAutomation
{
    public function run(bool $initial = false): array {
        return DB::transaction(function () use ($initial): array {
            // One durable row serializes deployment, scheduler and request catch-up runs.
            $state = DB::table('incident_archive_automation')->where('id', 1)->lockForUpdate()->first();
            if (! $state) throw new \RuntimeException('Archive automation state is not initialized. Run migrations first.');
            $month = now('Asia/Manila')->format('Y-m');
            if (($initial && $state->initial_completed_at) || (! $initial && $state->last_completed_month >= $month)) {
                return ['skipped' => true, 'counts' => ['fire' => 0, 'flood' => 0, 'observations' => 0], 'month' => $month];
            }
            $reason = $initial ? 'One-time archive of closed incidents older than three years, authorized by the system owner.'
                : 'Monthly automatic archive of closed incidents older than three years ('.$month.').';
            $service = app(IncidentArchiveService::class);
            $counts = [];
            foreach (array_keys(IncidentArchiveService::TYPES) as $type) {
                [$class] = $service->definition($type);
                $ids = $service->query($type, 'eligible')->orderBy('id')->lockForUpdate()->pluck('id')->all();
                $counts[$type] = count($ids);
                foreach (array_chunk($ids, 200) as $batch) {
                    // Metadata only: retain status, history, geometry, predictors and training flags.
                    $service->query($type, 'eligible')->whereKey($batch)->update([
                        'archived_at' => now(), 'archived_by' => null, 'archive_reason' => $reason,
                    ]);
                    app(\App\Services\Audit\AuditRecorder::class)->record([
                        'user_id' => null, 'user_name' => 'System', 'role_name' => 'Archive automation',
                        'action' => $initial ? 'initial_archive_incidents' : 'monthly_archive_incidents',
                        'module' => 'incident-archives', 'subject_type' => $class,
                        'description' => $reason.' Archived '.count($batch).' '.$type.' records; IDs are recorded in the details.',
                        'new_values' => ['record_ids' => $batch, 'archived_at' => now()->toIso8601String(), 'reason' => $reason],
                    ]);
                }
            }
            $result = ['skipped' => false, 'counts' => $counts, 'month' => $month, 'initial' => $initial];
            $values = ['last_completed_month' => $month, 'last_completed_at' => now(), 'last_result' => json_encode($result)];
            if ($initial) $values['initial_completed_at'] = now();
            DB::table('incident_archive_automation')->where('id', 1)->update($values);
            return $result;
        }, 3);
    }
}
