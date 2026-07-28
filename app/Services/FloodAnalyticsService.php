<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FloodAnalyticsService
{
    /**
     * Return all historical flood analytics required by the dashboard.
     */
    public function getDashboardData(
        ?int $year = null,
        ?string $barangay = null
    ): array {
        return [
            'filters' => [
                'year' => $year,
                'barangay' => $barangay,
            ],

            'kpis' => $this->getDashboardStats($year, $barangay),

            'monthly_trend' => $this->getMonthlyTrend(
                $year,
                $barangay
            ),

            'risk_distribution' => $this->getRiskDistribution(
                $year,
                $barangay
            ),

            'top_barangays' => $this->getTopBarangays(
                $year,
                $barangay
            ),

            'rainfall_trend' => $this->getRainfallTrend(
                $year,
                $barangay
            ),

            'season_distribution' => $this->getSeasonDistribution(
                $year,
                $barangay
            ),

            'day_distribution' => $this->getDayOfWeekDistribution(
                $year,
                $barangay
            ),

            'recent_records' => $this->getRecentRecords(
                $year,
                $barangay
            ),

            'available_years' => $this->getAvailableYears(),
        ];
    }

    /**
     * KPI cards for the historical flood dashboard.
     */
    public function getDashboardStats(
        ?int $year = null,
        ?string $barangay = null
    ): array {
        $query = $this->baseQuery($year, $barangay);

        $totalRecords = (clone $query)->count();

        $highRiskRecords = (clone $query)
            ->where('risk_level', 'High')
            ->count();

        $mediumRiskRecords = (clone $query)
            ->where('risk_level', 'Medium')
            ->count();

        $lowRiskRecords = (clone $query)
            ->where('risk_level', 'Low')
            ->count();

        $averageFloodDepth = (float) (
            (clone $query)
                ->whereNotNull('flood_depth_mm')
                ->avg('flood_depth_mm') ?? 0
        );

        $maximumFloodDepth = (float) (
            (clone $query)
                ->whereNotNull('flood_depth_mm')
                ->max('flood_depth_mm') ?? 0
        );

        $averageDuration = (float) (
            (clone $query)
                ->whereNotNull('duration_hours')
                ->avg('duration_hours') ?? 0
        );

        $averageRainfall24h = (float) (
            (clone $query)
                ->whereNotNull('rainfall_24h_mm')
                ->avg('rainfall_24h_mm') ?? 0
        );

        $wetSeasonRecords = (clone $query)
            ->where('wet_season', 1)
            ->count();

        $highestRiskBarangay = $this->getHighestRiskBarangay(
            $year,
            $barangay
        );

        return [
            'total_records' => $totalRecords,

            'high_risk_records' => $highRiskRecords,
            'medium_risk_records' => $mediumRiskRecords,
            'low_risk_records' => $lowRiskRecords,

            'high_risk_rate' => $totalRecords > 0
                ? round(($highRiskRecords / $totalRecords) * 100, 1)
                : 0,

            'average_flood_depth_mm' => round(
                $averageFloodDepth,
                2
            ),

            'average_flood_depth_label' => $this->formatDepth(
                $averageFloodDepth
            ),

            'maximum_flood_depth_mm' => round(
                $maximumFloodDepth,
                2
            ),

            'maximum_flood_depth_label' => $this->formatDepth(
                $maximumFloodDepth
            ),

            'average_duration_hours' => round(
                $averageDuration,
                2
            ),

            'average_duration_label' => $this->formatDuration(
                $averageDuration
            ),

            'average_rainfall_24h_mm' => round(
                $averageRainfall24h,
                2
            ),

            'wet_season_records' => $wetSeasonRecords,

            'wet_season_rate' => $totalRecords > 0
                ? round(($wetSeasonRecords / $totalRecords) * 100, 1)
                : 0,

            'highest_risk_barangay' => $highestRiskBarangay,
        ];
    }

    /**
     * Monthly flood frequency, depth, duration, rainfall, and risk.
     */
    public function getMonthlyTrend(
        ?int $year = null,
        ?string $barangay = null
    ): array {
        $rows = $this->baseQuery($year, $barangay)
            ->selectRaw('month as month_number')
            ->selectRaw('COUNT(*) as record_count')
            ->selectRaw(
                'COALESCE(AVG(flood_depth_mm), 0) as average_depth'
            )
            ->selectRaw(
                'COALESCE(AVG(duration_hours), 0) as average_duration'
            )
            ->selectRaw(
                'COALESCE(AVG(rainfall_24h_mm), 0) as average_rainfall'
            )
            ->selectRaw(
                "SUM(CASE WHEN risk_level = 'High' THEN 1 ELSE 0 END)
                as high_risk_count"
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month_number');

        $labels = [];
        $records = [];
        $averageDepth = [];
        $averageDuration = [];
        $averageRainfall = [];
        $highRisk = [];

        for ($month = 1; $month <= 12; $month++) {
            $row = $rows->get($month);

            $labels[] = date(
                'M',
                mktime(0, 0, 0, $month, 1)
            );

            $records[] = (int) ($row->record_count ?? 0);

            $averageDepth[] = round(
                (float) ($row->average_depth ?? 0),
                2
            );

            $averageDuration[] = round(
                (float) ($row->average_duration ?? 0),
                2
            );

            $averageRainfall[] = round(
                (float) ($row->average_rainfall ?? 0),
                2
            );

            $highRisk[] = (int) ($row->high_risk_count ?? 0);
        }

        return [
            'labels' => $labels,
            'records' => $records,
            'average_depth_mm' => $averageDepth,
            'average_duration_hours' => $averageDuration,
            'average_rainfall_24h_mm' => $averageRainfall,
            'high_risk_records' => $highRisk,
        ];
    }

    /**
     * Low, Medium, and High flood-risk distribution.
     */
    public function getRiskDistribution(
        ?int $year = null,
        ?string $barangay = null
    ): array {
        $rows = $this->baseQuery($year, $barangay)
            ->selectRaw('risk_level, COUNT(*) as total')
            ->groupBy('risk_level')
            ->pluck('total', 'risk_level');

        $labels = ['Low', 'Medium', 'High'];

        return [
            'labels' => $labels,

            'values' => collect($labels)
                ->map(
                    fn (string $label): int =>
                        (int) ($rows[$label] ?? 0)
                )
                ->all(),
        ];
    }

    /**
     * Rank barangays according to historical flood records.
     */
    public function getTopBarangays(
        ?int $year = null,
        ?string $barangay = null,
        int $limit = 10
    ): array {
        $rows = $this->baseQuery($year, $barangay)
            ->selectRaw('barangay')
            ->selectRaw('COUNT(*) as record_count')
            ->selectRaw(
                "SUM(CASE WHEN risk_level = 'High' THEN 1 ELSE 0 END)
                as high_risk_count"
            )
            ->selectRaw(
                'COALESCE(AVG(flood_depth_mm), 0) as average_depth'
            )
            ->selectRaw(
                'COALESCE(MAX(flood_depth_mm), 0) as maximum_depth'
            )
            ->selectRaw(
                'COALESCE(AVG(duration_hours), 0) as average_duration'
            )
            ->selectRaw(
                'COALESCE(AVG(rainfall_24h_mm), 0) as average_rainfall'
            )
            ->groupBy('barangay')
            ->orderByDesc('record_count')
            ->limit($limit)
            ->get();

        return [
            'labels' => $rows
                ->pluck('barangay')
                ->values()
                ->all(),

            'records' => $rows
                ->pluck('record_count')
                ->map(fn ($value): int => (int) $value)
                ->values()
                ->all(),

            'high_risk_records' => $rows
                ->pluck('high_risk_count')
                ->map(fn ($value): int => (int) $value)
                ->values()
                ->all(),

            'average_depth_mm' => $rows
                ->pluck('average_depth')
                ->map(
                    fn ($value): float =>
                        round((float) $value, 2)
                )
                ->values()
                ->all(),

            'rows' => $rows
                ->map(fn ($row): array => [
                    'barangay' => $row->barangay,

                    'record_count' => (int) $row->record_count,

                    'high_risk_count' => (int) $row->high_risk_count,

                    'high_risk_rate' => (int) $row->record_count > 0
                        ? round(
                            (
                                (int) $row->high_risk_count
                                / (int) $row->record_count
                            ) * 100,
                            1
                        )
                        : 0,

                    'average_depth_mm' => round(
                        (float) $row->average_depth,
                        2
                    ),

                    'maximum_depth_mm' => round(
                        (float) $row->maximum_depth,
                        2
                    ),

                    'average_duration_hours' => round(
                        (float) $row->average_duration,
                        2
                    ),

                    'average_rainfall_24h_mm' => round(
                        (float) $row->average_rainfall,
                        2
                    ),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Monthly rainfall measurements.
     */
    public function getRainfallTrend(
        ?int $year = null,
        ?string $barangay = null
    ): array {
        $rows = $this->baseQuery($year, $barangay)
            ->selectRaw('month as month_number')
            ->selectRaw(
                'COALESCE(AVG(rainfall_24h_mm), 0) as rainfall_24h'
            )
            ->selectRaw(
                'COALESCE(AVG(rainfall_3d_mm), 0) as rainfall_3d'
            )
            ->selectRaw(
                'COALESCE(AVG(rainfall_7d_mm), 0) as rainfall_7d'
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month_number');

        $labels = [];
        $rainfall24h = [];
        $rainfall3d = [];
        $rainfall7d = [];

        for ($month = 1; $month <= 12; $month++) {
            $row = $rows->get($month);

            $labels[] = date(
                'M',
                mktime(0, 0, 0, $month, 1)
            );

            $rainfall24h[] = round(
                (float) ($row->rainfall_24h ?? 0),
                2
            );

            $rainfall3d[] = round(
                (float) ($row->rainfall_3d ?? 0),
                2
            );

            $rainfall7d[] = round(
                (float) ($row->rainfall_7d ?? 0),
                2
            );
        }

        return [
            'labels' => $labels,
            'rainfall_24h_mm' => $rainfall24h,
            'rainfall_3d_mm' => $rainfall3d,
            'rainfall_7d_mm' => $rainfall7d,
        ];
    }

    /**
     * Compare wet-season and dry-season flood records.
     */
    public function getSeasonDistribution(
        ?int $year = null,
        ?string $barangay = null
    ): array {
        $rows = $this->baseQuery($year, $barangay)
            ->selectRaw(
                "CASE
                    WHEN wet_season = 1 THEN 'Wet Season'
                    ELSE 'Dry Season'
                END as season"
            )
            ->selectRaw('COUNT(*) as total')
            ->groupBy('wet_season')
            ->pluck('total', 'season');

        $labels = ['Wet Season', 'Dry Season'];

        return [
            'labels' => $labels,

            'values' => collect($labels)
                ->map(
                    fn (string $label): int =>
                        (int) ($rows[$label] ?? 0)
                )
                ->all(),
        ];
    }

    /**
     * Distribution of records according to day of the week.
     */
    public function getDayOfWeekDistribution(
        ?int $year = null,
        ?string $barangay = null
    ): array {
        $rows = $this->baseQuery($year, $barangay)
            ->selectRaw('day_of_week, COUNT(*) as total')
            ->groupBy('day_of_week')
            ->pluck('total', 'day_of_week');

        $labels = [
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday',
            'Sunday',
        ];

        return [
            'labels' => $labels,

            'values' => collect($labels)
                ->map(
                    fn (string $label): int =>
                        (int) ($rows[$label] ?? 0)
                )
                ->all(),
        ];
    }

    /**
     * Most recent historical flood records.
     */
    public function getRecentRecords(
        ?int $year = null,
        ?string $barangay = null,
        int $limit = 10
    ): Collection {
        return $this->baseQuery($year, $barangay)
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get([
                'id',
                'event_id',
                'event_date',
                'barangay',
                'nearest_waterway',
                'rainfall_24h_mm',
                'rainfall_3d_mm',
                'rainfall_7d_mm',
                'flood_depth_mm',
                'duration_hours',
                'risk_level',
                'wet_season',
                'storm_signal',
            ]);
    }

    /**
     * Years represented in the historical dataset.
     */
    public function getAvailableYears(): array
    {
        return DB::table('flood_analytics_dataset')
            ->whereNotNull('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year): int => (int) $year)
            ->values()
            ->all();
    }

    /**
     * Apply common dashboard filters to the dataset.
     */
    private function baseQuery(
        ?int $year = null,
        ?string $barangay = null
    ): Builder {
        return DB::table('flood_analytics_dataset')
            ->when(
                $year,
                fn (Builder $query): Builder =>
                    $query->where('year', $year)
            )
            ->when(
                $barangay,
                fn (Builder $query): Builder =>
                    $query->where('barangay', $barangay)
            );
    }

    /**
     * Find the barangay with the most High-risk historical records.
     */
    private function getHighestRiskBarangay(
        ?int $year = null,
        ?string $barangay = null
    ): ?array {
        $row = $this->baseQuery($year, $barangay)
            ->selectRaw('barangay')
            ->selectRaw('COUNT(*) as total_records')
            ->selectRaw(
                "SUM(CASE WHEN risk_level = 'High' THEN 1 ELSE 0 END)
                as high_risk_count"
            )
            ->selectRaw(
                'COALESCE(AVG(flood_depth_mm), 0) as average_depth'
            )
            ->groupBy('barangay')
            ->orderByDesc('high_risk_count')
            ->orderByDesc('total_records')
            ->first();

        if (!$row) {
            return null;
        }

        return [
            'barangay' => $row->barangay,

            'total_records' => (int) $row->total_records,

            'high_risk_count' => (int) $row->high_risk_count,

            'high_risk_rate' => (int) $row->total_records > 0
                ? round(
                    (
                        (int) $row->high_risk_count
                        / (int) $row->total_records
                    ) * 100,
                    1
                )
                : 0,

            'average_depth_mm' => round(
                (float) $row->average_depth,
                2
            ),
        ];
    }

    /**
     * Convert millimetres to a readable depth label.
     */
    private function formatDepth(float $millimetres): string
    {
        if ($millimetres <= 0) {
            return '0 mm';
        }

        if ($millimetres < 1000) {
            return round($millimetres, 1) . ' mm';
        }

        return round($millimetres / 1000, 2) . ' m';
    }

    /**
     * Convert decimal hours into a readable duration.
     */
    private function formatDuration(float $hours): string
    {
        if ($hours <= 0) {
            return '0 hr';
        }

        $wholeHours = (int) floor($hours);

        $minutes = (int) round(
            ($hours - $wholeHours) * 60
        );

        if ($minutes === 60) {
            $wholeHours++;
            $minutes = 0;
        }

        if ($wholeHours === 0) {
            return "{$minutes} min";
        }

        if ($minutes === 0) {
            return "{$wholeHours} hr";
        }

        return "{$wholeHours} hr {$minutes} min";
    }
}