<?php

namespace Database\Seeders;

use App\Models\Barangay;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use SplFileObject;

class BarangayFloodProfileSeeder extends Seeder
{
    /**
     * CSV column names required for generating barangay profiles.
     */
    private const REQUIRED_COLUMNS = [
        'barangay',
        'nearest_waterway',
        'elevation_m',
        'distance_to_waterway_m',
        'drainage_index',
        'impervious_surface_ratio',
        'population_density_per_km2',
        'historical_flood_count_5y',
    ];

    /**
     * Resolve naming differences between the CSV and database.
     */
    private const BARANGAY_ALIASES = [
        'New Zaniga' => 'New Zañiga',
        'New Zañiga' => 'New Zañiga',

        'Old Zaniga' => 'Old Zañiga',
        'Old Zañiga' => 'Old Zañiga',

        'Pag Asa' => 'Pag-Asa',
        'Pag-Asa' => 'Pag-Asa',

        'Mabini J. Rizal' => 'Mabini-J. Rizal',
        'Mabini-J Rizal' => 'Mabini-J. Rizal',

        'Harapin Ang Bukas' => 'Harapin ang Bukas',

        'Wack-Wack Greenhills East' =>
            'Wack-Wack Greenhills',

        'Wack-Wack Greenhills' =>
            'Wack-Wack Greenhills',
    ];

    public function run(): void
    {
        $csvPath = storage_path(
            'app/datasets/dataset-flood.csv'
        );

        if (! file_exists($csvPath)) {
            throw new RuntimeException(
                "Dataset file not found: {$csvPath}"
            );
        }

        $rows = $this->readCsv($csvPath);

        if ($rows->isEmpty()) {
            throw new RuntimeException(
                'The flood dataset contains no valid records.'
            );
        }

        $profiles = $rows
            ->groupBy('barangay')
            ->map(
                fn (Collection $records, string $barangay): array =>
                    $this->buildProfile(
                        $barangay,
                        $records
                    )
            );

        $updated = 0;
        $missing = [];

        foreach ($profiles as $barangay => $profile) {
            $databaseBarangay = Barangay::query()
                ->whereRaw(
                    'LOWER(TRIM(name)) = ?',
                    [mb_strtolower(trim($barangay))]
                )
                ->first();

            if (! $databaseBarangay) {
                $missing[] = $barangay;

                Log::warning(
                    'Barangay from flood dataset was not found.',
                    ['barangay' => $barangay]
                );

                continue;
            }

            $databaseBarangay->update([
                'elevation_m' =>
                    $profile['elevation_m'],

                'nearest_waterway' =>
                    $profile['nearest_waterway'],

                'distance_to_waterway_m' =>
                    $profile['distance_to_waterway_m'],

                'drainage_index' =>
                    $profile['drainage_index'],

                'impervious_surface_ratio' =>
                    $profile['impervious_surface_ratio'],

                'population_density_per_km2' =>
                    $profile[
                        'population_density_per_km2'
                    ],

                'historical_flood_count_5y' =>
                    $profile[
                        'historical_flood_count_5y'
                    ],
            ]);

            $updated++;
        }

        $this->command?->info(
            "Updated {$updated} barangay flood profiles."
        );

        if ($missing !== []) {
            $this->command?->warn(
                'Unmatched barangays: ' .
                implode(', ', $missing)
            );
        }
    }

    /**
     * Read and normalize CSV records.
     */
    private function readCsv(
        string $csvPath
    ): Collection {
        $file = new SplFileObject($csvPath);

        $file->setFlags(
            SplFileObject::READ_CSV
            | SplFileObject::SKIP_EMPTY
            | SplFileObject::DROP_NEW_LINE
        );

        $header = null;
        $records = collect();

        foreach ($file as $row) {
            if (
                ! is_array($row)
                || $row === [null]
                || count($row) === 0
            ) {
                continue;
            }

            if ($header === null) {
                $header = array_map(
                    fn ($column): string =>
                        trim(
                            preg_replace(
                                '/^\xEF\xBB\xBF/',
                                '',
                                (string) $column
                            )
                        ),
                    $row
                );

                $this->validateHeader($header);

                continue;
            }

            if (count($row) !== count($header)) {
                continue;
            }

            $record = array_combine(
                $header,
                $row
            );

            if (! is_array($record)) {
                continue;
            }

            $barangay = $this->normalizeBarangayName(
                (string) (
                    $record['barangay'] ?? ''
                )
            );

            if ($barangay === '') {
                continue;
            }

            $records->push([
                'barangay' => $barangay,

                'nearest_waterway' =>
                    $this->nullableString(
                        $record['nearest_waterway'] ?? null
                    ),

                'elevation_m' =>
                    $this->nullableFloat(
                        $record['elevation_m'] ?? null
                    ),

                'distance_to_waterway_m' =>
                    $this->nullableFloat(
                        $record[
                            'distance_to_waterway_m'
                        ] ?? null
                    ),

                'drainage_index' =>
                    $this->nullableFloat(
                        $record['drainage_index'] ?? null
                    ),

                'impervious_surface_ratio' =>
                    $this->nullableFloat(
                        $record[
                            'impervious_surface_ratio'
                        ] ?? null
                    ),

                'population_density_per_km2' =>
                    $this->nullableFloat(
                        $record[
                            'population_density_per_km2'
                        ] ?? null
                    ),

                'historical_flood_count_5y' =>
                    $this->nullableFloat(
                        $record[
                            'historical_flood_count_5y'
                        ] ?? null
                    ),
            ]);
        }

        return $records;
    }

    /**
     * Generate one stable reference profile per barangay.
     */
    private function buildProfile(
        string $barangay,
        Collection $records
    ): array {
        return [
            'barangay' => $barangay,

            // Categorical profile: most frequent value.
            'nearest_waterway' =>
                $this->mode(
                    $records->pluck(
                        'nearest_waterway'
                    )
                ),

            // Numeric profiles: median prevents extreme records
            // from distorting the barangay's master values.
            'elevation_m' => round(
                $this->median(
                    $records->pluck('elevation_m')
                ),
                2
            ),

            'distance_to_waterway_m' => round(
                $this->median(
                    $records->pluck(
                        'distance_to_waterway_m'
                    )
                ),
                2
            ),

            'drainage_index' => round(
                $this->median(
                    $records->pluck(
                        'drainage_index'
                    )
                ),
                4
            ),

            'impervious_surface_ratio' => round(
                $this->median(
                    $records->pluck(
                        'impervious_surface_ratio'
                    )
                ),
                4
            ),

            'population_density_per_km2' => round(
                $this->median(
                    $records->pluck(
                        'population_density_per_km2'
                    )
                ),
                2
            ),

            'historical_flood_count_5y' => (int) round(
                $this->median(
                    $records->pluck(
                        'historical_flood_count_5y'
                    )
                )
            ),
        ];
    }

    private function validateHeader(
        array $header
    ): void {
        $missing = array_diff(
            self::REQUIRED_COLUMNS,
            $header
        );

        if ($missing !== []) {
            throw new RuntimeException(
                'Dataset is missing columns: ' .
                implode(', ', $missing)
            );
        }
    }

    private function normalizeBarangayName(
        string $name
    ): string {
        $normalized = trim(
            preg_replace('/\s+/', ' ', $name)
        );

        return self::BARANGAY_ALIASES[
            $normalized
        ] ?? $normalized;
    }

    private function nullableString(
        mixed $value
    ): ?string {
        $value = trim((string) $value);

        if (
            $value === ''
            || strtolower($value) === 'null'
            || strtolower($value) === 'nan'
        ) {
            return null;
        }

        return $value;
    }

    private function nullableFloat(
        mixed $value
    ): ?float {
        if (
            $value === null
            || $value === ''
            || ! is_numeric($value)
        ) {
            return null;
        }

        return (float) $value;
    }

    private function median(
        Collection $values
    ): float {
        $values = $values
            ->filter(
                fn ($value): bool =>
                    $value !== null
                    && is_numeric($value)
            )
            ->map(
                fn ($value): float =>
                    (float) $value
            )
            ->sort()
            ->values();

        $count = $values->count();

        if ($count === 0) {
            return 0.0;
        }

        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return $values[$middle];
        }

        return (
            $values[$middle - 1]
            + $values[$middle]
        ) / 2;
    }

    private function mode(
        Collection $values
    ): ?string {
        $values = $values
            ->filter(
                fn ($value): bool =>
                    is_string($value)
                    && trim($value) !== ''
            )
            ->map(
                fn (string $value): string =>
                    trim($value)
            );

        if ($values->isEmpty()) {
            return null;
        }

        return $values
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();
    }
}