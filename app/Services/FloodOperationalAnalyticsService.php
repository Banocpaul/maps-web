<?php

namespace App\Services;

use App\Models\FloodIncidentRecord;
use App\Models\FloodTrainingRecord;
use Illuminate\Support\Carbon;

class FloodOperationalAnalyticsService
{
    public function build(?int $year = null, ?string $barangay = null): array
    {
        $records = FloodTrainingRecord::query()->when($year, fn ($q) => $q->whereYear('observed_at', $year))
            ->when($barangay, fn ($q) => $q->where('barangay', $barangay))
            ->get(['barangay', 'observed_at', 'subsided_at', 'flood_level_code', 'flood_status']);
        $operational = FloodIncidentRecord::query()->when($year, fn ($q) => $q->where('year', $year))
            ->when($barangay, fn ($q) => $q->where('barangay', $barangay))
            ->get(['barangay', 'flood_start_datetime', 'flood_subsided_datetime', 'flood_code', 'status'])
            ->map(fn ($r) => (object) ['barangay' => $r->barangay,
                'observed_at' => $r->flood_start_datetime ? Carbon::parse($r->flood_start_datetime, 'Asia/Manila') : null,
                'subsided_at' => $r->flood_subsided_datetime ? Carbon::parse($r->flood_subsided_datetime, 'Asia/Manila') : null,
                'flood_level_code' => $r->flood_code, 'flood_status' => $r->status]);
        $sourceCounts = ['incident_records' => $operational->count(), 'field_observations' => $records->count()];
        $records = collect($records->all())->concat($operational);
        $priority = $records->groupBy('barangay')->map(function ($group, $name): array {
            $known = $group->whereIn('flood_level_code', ['A', 'B', 'C', 'D']);
            $severe = $known->whereIn('flood_level_code', ['C', 'D'])->count();
            $durations = $group->filter(fn ($r) => $r->flood_status === 'Subsided' && $r->observed_at && $r->subsided_at && $r->subsided_at->gte($r->observed_at))
                ->map(fn ($r) => $r->observed_at->diffInSeconds($r->subsided_at) / 3600);

            return ['barangay' => $name, 'count' => $group->count(), 'severe' => $severe,
                'known' => $known->count(), 'rate' => $known->count() ? round($severe * 100 / $known->count(), 1) : null,
                'median_hours' => $durations->isNotEmpty() ? round($durations->median(), 1) : null,
                'duration_samples' => $durations->count(),
                'codes' => collect(['A', 'B', 'C', 'D'])->mapWithKeys(fn ($code) => [$code => $known->where('flood_level_code', $code)->count()])->all()];
        })->sortByDesc('count')->values()->all();
        $monthly = array_fill(0, 12, 0);
        foreach ($records as $record) {
            if ($record->observed_at) {
                $monthly[$record->observed_at->month - 1]++;
            }
        }

        return ['source_counts' => $sourceCounts, 'total' => $records->count(), 'active' => $records->where('flood_status', 'Active')->count(),
            'subsided' => $records->where('flood_status', 'Subsided')->count(), 'priority' => $priority, 'monthly' => $monthly,
            'unknown_codes' => $records->whereNotIn('flood_level_code', ['A', 'B', 'C', 'D'])->count()];
    }
}
