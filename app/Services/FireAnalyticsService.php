<?php

namespace App\Services;

use App\Models\FireIncident;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FireAnalyticsService
{
    /**
     * Return all analytics data needed by the Fire BI dashboard.
     */
    public function getDashboardData(?int $year = null, ?int $barangayId = null): array
    {
        return [
            'filters' => [
                'year' => $year,
                'barangay_id' => $barangayId,
            ],
            'kpis' => $this->getDashboardStats($year, $barangayId),
            'monthly_trend' => $this->getMonthlyTrend($year, $barangayId),
            'top_barangays' => $this->getTopBarangays($year, $barangayId),
            'severity_distribution' => $this->getSeverityDistribution($year, $barangayId),
            'alarm_distribution' => $this->getAlarmLevelDistribution($year, $barangayId),
            'time_distribution' => $this->getTimeOfDayDistribution($year, $barangayId),
            'affected_by_barangay' => $this->getAffectedPeopleByBarangay($year, $barangayId),
            'houses_destroyed_by_barangay' => $this->getHousesDestroyedByBarangay($year, $barangayId),
            'recent_incidents' => $this->getRecentIncidents($year, $barangayId),
            'available_years' => $this->getAvailableYears(),
        ];
    }

    /**
     * KPI cards for the dashboard.
     */
    public function getDashboardStats(?int $year = null, ?int $barangayId = null): array
    {
        $query = $this->baseQuery($year, $barangayId);

        $totalIncidents = (clone $query)->count();

        $individualsAffected = (int) (clone $query)
            ->sum('individuals_affected');

        $housesDestroyed = (int) (clone $query)
            ->sum('houses_destroyed');

        $averageDuration = (float) ((clone $query)
            ->whereNotNull('duration_minutes')
            ->avg('duration_minutes') ?? 0);

        $majorIncidents = (clone $query)
            ->where('severity', 'Major')
            ->count();

        $resolvedIncidents = (clone $query)
            ->where('status', 'Resolved')
            ->count();

        $highestIncidentBarangay = $this->getHighestIncidentBarangay(
            $year,
            $barangayId
        );

        return [
            'total_incidents' => $totalIncidents,
            'individuals_affected' => $individualsAffected,
            'houses_destroyed' => $housesDestroyed,
            'average_duration_minutes' => round($averageDuration, 1),
            'average_duration_label' => $this->formatDuration($averageDuration),
            'major_incidents' => $majorIncidents,
            'resolved_incidents' => $resolvedIncidents,
            'resolution_rate' => $totalIncidents > 0
                ? round(($resolvedIncidents / $totalIncidents) * 100, 1)
                : 0,
            'highest_incident_barangay' => $highestIncidentBarangay,
        ];
    }

    /**
     * Monthly incident count and BI metrics.
     */
    public function getMonthlyTrend(?int $year = null, ?int $barangayId = null): array
    {
        $rows = $this->baseQuery($year, $barangayId)
            ->selectRaw('MONTH(occurred_at) as month_number')
            ->selectRaw('COUNT(*) as incident_count')
            ->selectRaw('COALESCE(SUM(individuals_affected), 0) as affected_count')
            ->selectRaw('COALESCE(SUM(houses_destroyed), 0) as destroyed_count')
            ->selectRaw('COALESCE(AVG(duration_minutes), 0) as average_duration')
            ->whereNotNull('occurred_at')
            ->groupByRaw('MONTH(occurred_at)')
            ->orderByRaw('MONTH(occurred_at)')
            ->get()
            ->keyBy('month_number');

        $labels = [];
        $incidents = [];
        $affected = [];
        $destroyed = [];
        $averageDuration = [];

        for ($month = 1; $month <= 12; $month++) {
            $row = $rows->get($month);

            $labels[] = date('M', mktime(0, 0, 0, $month, 1));
            $incidents[] = (int) ($row->incident_count ?? 0);
            $affected[] = (int) ($row->affected_count ?? 0);
            $destroyed[] = (int) ($row->destroyed_count ?? 0);
            $averageDuration[] = round((float) ($row->average_duration ?? 0), 1);
        }

        return [
            'labels' => $labels,
            'incidents' => $incidents,
            'individuals_affected' => $affected,
            'houses_destroyed' => $destroyed,
            'average_duration_minutes' => $averageDuration,
        ];
    }

    /**
     * Rank barangays by number of fire incidents.
     */
    public function getTopBarangays(
        ?int $year = null,
        ?int $barangayId = null,
        int $limit = 10
    ): array {
        $rows = $this->baseQuery($year, $barangayId)
            ->join('barangays', 'barangays.id', '=', 'fire_incidents.barangay_id')
            ->selectRaw('barangays.id as barangay_id')
            ->selectRaw('barangays.name as barangay_name')
            ->selectRaw('COUNT(fire_incidents.id) as incident_count')
            ->selectRaw('COALESCE(SUM(fire_incidents.individuals_affected), 0) as affected_count')
            ->selectRaw('COALESCE(SUM(fire_incidents.houses_destroyed), 0) as destroyed_count')
            ->groupBy('barangays.id', 'barangays.name')
            ->orderByDesc('incident_count')
            ->limit($limit)
            ->get();

        return [
            'labels' => $rows->pluck('barangay_name')->values()->all(),
            'incidents' => $rows->pluck('incident_count')->map(fn ($value) => (int) $value)->values()->all(),
            'individuals_affected' => $rows->pluck('affected_count')->map(fn ($value) => (int) $value)->values()->all(),
            'houses_destroyed' => $rows->pluck('destroyed_count')->map(fn ($value) => (int) $value)->values()->all(),
            'rows' => $rows->map(fn ($row) => [
                'barangay_id' => (int) $row->barangay_id,
                'barangay_name' => $row->barangay_name,
                'incident_count' => (int) $row->incident_count,
                'individuals_affected' => (int) $row->affected_count,
                'houses_destroyed' => (int) $row->destroyed_count,
            ])->values()->all(),
        ];
    }

    /**
     * Incident distribution by Minor, Moderate, and Major severity.
     */
    public function getSeverityDistribution(?int $year = null, ?int $barangayId = null): array
    {
        $rows = $this->baseQuery($year, $barangayId)
            ->selectRaw('severity, COUNT(*) as total')
            ->groupBy('severity')
            ->pluck('total', 'severity');

        $labels = ['Minor', 'Moderate', 'Major'];

        return [
            'labels' => $labels,
            'values' => collect($labels)
                ->map(fn ($label) => (int) ($rows[$label] ?? 0))
                ->all(),
        ];
    }

    /**
     * Incident distribution by alarm level.
     */
    public function getAlarmLevelDistribution(?int $year = null, ?int $barangayId = null): array
    {
        $rows = $this->baseQuery($year, $barangayId)
            ->selectRaw("COALESCE(NULLIF(TRIM(alarm_level), ''), 'Unspecified') as alarm_label")
            ->selectRaw('COUNT(*) as total')
            ->groupBy('alarm_label')
            ->orderByDesc('total')
            ->get();

        return [
            'labels' => $rows->pluck('alarm_label')->values()->all(),
            'values' => $rows->pluck('total')->map(fn ($value) => (int) $value)->values()->all(),
        ];
    }

    /**
     * Group incidents by time of day.
     */
    public function getTimeOfDayDistribution(?int $year = null, ?int $barangayId = null): array
    {
        $rows = $this->baseQuery($year, $barangayId)
            ->whereNotNull('occurred_at')
            ->selectRaw("
                CASE
                    WHEN HOUR(occurred_at) BETWEEN 5 AND 11 THEN 'Morning'
                    WHEN HOUR(occurred_at) BETWEEN 12 AND 16 THEN 'Afternoon'
                    WHEN HOUR(occurred_at) BETWEEN 17 AND 20 THEN 'Evening'
                    ELSE 'Night'
                END as time_period
            ")
            ->selectRaw('COUNT(*) as total')
            ->groupBy('time_period')
            ->get()
            ->pluck('total', 'time_period');

        $labels = ['Morning', 'Afternoon', 'Evening', 'Night'];

        return [
            'labels' => $labels,
            'values' => collect($labels)
                ->map(fn ($label) => (int) ($rows[$label] ?? 0))
                ->all(),
        ];
    }

    /**
     * Rank barangays by affected individuals.
     */
    public function getAffectedPeopleByBarangay(
        ?int $year = null,
        ?int $barangayId = null,
        int $limit = 10
    ): array {
        $rows = $this->baseQuery($year, $barangayId)
            ->join('barangays', 'barangays.id', '=', 'fire_incidents.barangay_id')
            ->selectRaw('barangays.name as barangay_name')
            ->selectRaw('COALESCE(SUM(fire_incidents.individuals_affected), 0) as total')
            ->groupBy('barangays.id', 'barangays.name')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        return [
            'labels' => $rows->pluck('barangay_name')->values()->all(),
            'values' => $rows->pluck('total')->map(fn ($value) => (int) $value)->values()->all(),
        ];
    }

    /**
     * Rank barangays by destroyed houses.
     */
    public function getHousesDestroyedByBarangay(
        ?int $year = null,
        ?int $barangayId = null,
        int $limit = 10
    ): array {
        $rows = $this->baseQuery($year, $barangayId)
            ->join('barangays', 'barangays.id', '=', 'fire_incidents.barangay_id')
            ->selectRaw('barangays.name as barangay_name')
            ->selectRaw('COALESCE(SUM(fire_incidents.houses_destroyed), 0) as total')
            ->groupBy('barangays.id', 'barangays.name')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        return [
            'labels' => $rows->pluck('barangay_name')->values()->all(),
            'values' => $rows->pluck('total')->map(fn ($value) => (int) $value)->values()->all(),
        ];
    }

    /**
     * Return the most recent fire incident records.
     */
    public function getRecentIncidents(
        ?int $year = null,
        ?int $barangayId = null,
        int $limit = 10
    ): Collection {
        return $this->baseQuery($year, $barangayId)
            ->with('barangay:id,name')
            ->orderByDesc('occurred_at')
            ->orderByDesc('reported_at')
            ->limit($limit)
            ->get([
                'id',
                'barangay_id',
                'incident_number',
                'incident_type',
                'location',
                'severity',
                'status',
                'occurred_at',
                'fire_out_at',
                'duration_minutes',
                'individuals_affected',
                'houses_destroyed',
                'alarm_level',
            ]);
    }

    /**
     * Years available in the historical dataset.
     */
    public function getAvailableYears(): array
    {
        return FireIncident::query()
            ->whereNotNull('occurred_at')
            ->selectRaw('YEAR(occurred_at) as year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->values()
            ->all();
    }

    /**
     * Apply common filters to every analytics query.
     */
    private function baseQuery(?int $year = null, ?int $barangayId = null): Builder
    {
        return FireIncident::query()
            ->when(
                $year,
                fn (Builder $query) => $query->whereYear('occurred_at', $year)
            )
            ->when(
                $barangayId,
                fn (Builder $query) => $query->where('barangay_id', $barangayId)
            );
    }

    /**
     * Find the barangay with the highest incident count.
     */
    private function getHighestIncidentBarangay(
        ?int $year = null,
        ?int $barangayId = null
    ): ?array {
        $row = $this->baseQuery($year, $barangayId)
            ->join('barangays', 'barangays.id', '=', 'fire_incidents.barangay_id')
            ->selectRaw('barangays.id as barangay_id')
            ->selectRaw('barangays.name as barangay_name')
            ->selectRaw('COUNT(fire_incidents.id) as incident_count')
            ->groupBy('barangays.id', 'barangays.name')
            ->orderByDesc('incident_count')
            ->first();

        if (!$row) {
            return null;
        }

        return [
            'barangay_id' => (int) $row->barangay_id,
            'barangay_name' => $row->barangay_name,
            'incident_count' => (int) $row->incident_count,
        ];
    }

    /**
     * Convert minutes into a human-readable duration.
     */
    private function formatDuration(float $minutes): string
    {
        $roundedMinutes = (int) round($minutes);

        if ($roundedMinutes <= 0) {
            return '0 min';
        }

        $hours = intdiv($roundedMinutes, 60);
        $remainingMinutes = $roundedMinutes % 60;

        if ($hours === 0) {
            return "{$remainingMinutes} min";
        }

        if ($remainingMinutes === 0) {
            return "{$hours} hr";
        }

        return "{$hours} hr {$remainingMinutes} min";
    }
}