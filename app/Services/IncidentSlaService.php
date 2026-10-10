<?php

namespace App\Services;

use App\Models\PublicIncidentReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class IncidentSlaService
{
    public function build(string $hazard, ?int $year = null, ?int $barangayId = null): array
    {
        $target = (array) DB::table('incident_sla_targets')->where('hazard', $hazard)->first();
        $stages = [];
        foreach (['validation' => 'Submission → validation', 'publication' => 'Validation → posting', 'completion' => 'Submission → completion'] as $key => $label) {
            $stages[$key] = ['label' => $label, 'target' => $target[$key.'_minutes'] ?? null,
                'durations' => [], 'within' => 0, 'overdue' => 0, 'waiting' => 0, 'invalid' => 0];
        }
        $total = $rejected = $unknownCompletion = 0;
        $attention = [];
        // Submission-year cohort; barangay uses the incident location after posting and reporter barangay before posting.
        $reports = PublicIncidentReport::query()->where('incident_type', $hazard)
            ->when($year, fn ($q) => $q->whereYear('created_at', $year))
            ->when($barangayId, function ($q) use ($barangayId, $hazard): void {
                $q->where(function ($q) use ($barangayId, $hazard): void {
                    $q->whereHas($hazard === 'fire' ? 'fireIncident' : 'floodTrainingRecord', function ($q) use ($barangayId, $hazard): void {
                        $hazard === 'fire' ? $q->where('barangay_id', $barangayId)
                            : $q->where('barangay', DB::table('barangays')->where('id', $barangayId)->value('name'));
                    })->orWhere(function ($q) use ($barangayId): void {
                        $q->whereNull('fire_incident_id')->whereNull('flood_training_record_id')->where('reporter_barangay_id', $barangayId);
                    });
                });
            })->with(['fireIncident.barangay', 'floodTrainingRecord', 'reporterBarangay'])->orderBy('id');
        foreach ($reports->lazy(250) as $report) {
            $total++;
            if ($report->status === 'Rejected') {
                $rejected++;

                continue;
            }
            $incident = $hazard === 'fire' ? $report->fireIncident : $report->floodTrainingRecord;
            $closed = $incident && ($hazard === 'fire' ? $incident->record_status === 'Finalized' : $incident->flood_status === 'Subsided');
            $completion = $closed ? ($hazard === 'fire' ? $incident->finalized_at : $incident->handling_completed_at) : null;
            $unavailable = $report->status === 'Published' && ! $incident;
            $missingClosedTime = ($closed && ! $completion) || $unavailable;
            if ($missingClosedTime) {
                $unknownCompletion++;
            }
            $intervals = [
                'validation' => [$report->created_at, $report->validated_at, true],
                'publication' => [$report->validated_at, $report->published_at, $report->validated_at !== null],
                'completion' => [$report->created_at, $completion, ! $missingClosedTime],
            ];
            $waitingStage = $report->validated_at === null ? 'Validation' : ($report->published_at === null ? 'Posting' : ($hazard === 'fire' ? 'Fire finalization' : 'Flood completion'));
            $breached = false;
            foreach ($intervals as $key => [$start, $end, $eligible]) {
                if (! $eligible || ! $start) {
                    continue;
                }
                $minutes = $this->minutes($start, $end ?? now());
                if ($minutes === null) {
                    $stages[$key]['invalid']++;

                    continue;
                }
                if ($end) {
                    $stages[$key]['durations'][] = $minutes;
                    if ($stages[$key]['target'] !== null && $minutes <= $stages[$key]['target']) {
                        $stages[$key]['within']++;
                    }
                } else {
                    $stages[$key]['waiting']++;
                    if ($stages[$key]['target'] !== null && $minutes > $stages[$key]['target']) {
                        $stages[$key]['overdue']++;
                        $breached = true;
                    }
                }
            }
            if (! $closed && ! $unavailable && $this->minutes($report->created_at, now()) !== null) {
                $attention[] = ['id' => $report->id, 'reference' => $report->reference, 'stage' => $waitingStage,
                    'minutes' => round($this->minutes($report->created_at, now()), 1), 'overdue' => $breached,
                    'barangay' => $hazard === 'fire' ? ($incident?->barangay?->name ?? $report->reporterBarangay?->name ?? 'Unassigned') : ($incident?->barangay ?? $report->reporterBarangay?->name ?? 'Unassigned')];
            }
        }
        foreach ($stages as &$stage) {
            sort($stage['durations']);
            $stage['completed'] = count($stage['durations']);
            $stage['median'] = $this->percentile($stage['durations'], .5);
            $stage['p90'] = $this->percentile($stage['durations'], .9);
            $stage['compliance'] = $stage['target'] !== null && $stage['completed'] > 0
                ? round(100 * $stage['within'] / $stage['completed'], 1) : null;
            unset($stage['durations']);
        }
        unset($stage);
        usort($attention, fn ($a, $b) => ($b['overdue'] <=> $a['overdue']) ?: ($b['minutes'] <=> $a['minutes']));

        return ['total' => $total, 'rejected' => $rejected, 'unknown_completion' => $unknownCompletion,
            'stages' => $stages, 'attention' => array_slice($attention, 0, 10)];
    }

    private function minutes($start, $end): ?float
    {
        $start = Carbon::parse($start);
        $end = Carbon::parse($end);

        return ($end->lt($start) || $end->gt(now())) ? null : $start->diffInSeconds($end) / 60;
    }

    private function percentile(array $sorted, float $p): ?float
    {
        if (! $sorted) {
            return null;
        }
        $index = (count($sorted) - 1) * $p;
        $lower = (int) floor($index);
        $upper = (int) ceil($index);

        return round($sorted[$lower] + ($sorted[$upper] - $sorted[$lower]) * ($index - $lower), 1);
    }
}
