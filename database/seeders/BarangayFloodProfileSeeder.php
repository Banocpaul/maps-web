<?php

namespace Database\Seeders;

use App\Models\Barangay;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SplFileObject;

class BarangayFloodProfileSeeder extends Seeder
{
    private const NUMERIC_FIELDS = [
        'elevation_m' => 2,
        'distance_to_waterway_m' => 2,
        'drainage_index' => 2,
        'impervious_surface_ratio' => 2,
        'population_density_per_km2' => 0,
        'historical_flood_count_5y' => 0,
    ];

    private const INTEGER_FIELDS = [
        'population_density_per_km2',
        'historical_flood_count_5y',
    ];

    // Names on the right match your actual barangays table.
    private const ALIASES = [
        'New Zaniga' => 'New Zañiga',
        'Old Zaniga' => 'Old Zañiga',
        'Pag Asa' => 'Pag-Asa',
        'Mabini J. Rizal' => 'Mabini-J. Rizal',
        'Mabini-J Rizal' => 'Mabini-J. Rizal',
        'Harapin Ang Bukas' => 'Harapin ang Bukas',
        'Hagdan Bato Itaas' => 'Hagdang Bato Itaas',
        'Hagdan Bato Libis' => 'Hagdang Bato Libis',
        'Wack-Wack Greenhills' => 'Wack-Wack Greenhills East',
    ];

    public function run(): void
    {
        $selectedPath = getenv('FLOOD_PROFILE_DATASET');

        $path = is_string($selectedPath) && trim($selectedPath) !== ''
            ? trim($selectedPath)
            : storage_path('app/datasets/dataset-flood.csv');

        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException(
                "Dataset file is missing or unreadable: {$path}"
            );
        }

        $rows = $this->readCsv($path);

        if ($rows->isEmpty()) {
            throw new RuntimeException('The dataset contains no records.');
        }

        $updates = [];
        $unmatched = [];

        // Validate every proposed change before writing to the database.
        foreach ($rows->groupBy('barangay') as $name => $records) {
            $barangay = Barangay::query()
                ->whereRaw(
                    'LOWER(TRIM(name)) = ?',
                    [mb_strtolower(trim($name))]
                )
                ->first();

            if ($barangay === null) {
                $unmatched[] = $name;
                continue;
            }

            $changes = [];

            if ($this->isMissing($barangay->nearest_waterway)) {
                $values = $records
                    ->pluck('nearest_waterway')
                    ->map(fn ($value) => trim((string) $value))
                    ->reject(fn ($value) => $this->isMissing($value));

                if ($values->isEmpty()) {
                    throw new RuntimeException(
                        "{$name}: nearest_waterway is missing in the CSV."
                    );
                }

                $changes['nearest_waterway'] = (string) $values
                    ->countBy()
                    ->sortDesc()
                    ->keys()
                    ->first();
            }

            foreach (self::NUMERIC_FIELDS as $field => $precision) {
                if (! $this->isMissing($barangay->{$field})) {
                    continue;
                }

                $values = $records
                    ->pluck($field)
                    ->reject(fn ($value) => $this->isMissing($value));

                if ($values->isEmpty()) {
                    throw new RuntimeException(
                        "{$name}: {$field} is missing in the CSV."
                    );
                }

                foreach ($values as $value) {
                    if (
                        ! is_numeric($value)
                        || ! is_finite((float) $value)
                    ) {
                        throw new RuntimeException(
                            "{$name}: {$field} contains an invalid number."
                        );
                    }

                    if ($field !== 'elevation_m' && (float) $value < 0) {
                        throw new RuntimeException(
                            "{$name}: {$field} contains a negative value."
                        );
                    }
                }

                $median = (float) $values
                    ->map(fn ($value) => (float) $value)
                    ->median();

                $changes[$field] = in_array(
                    $field,
                    self::INTEGER_FIELDS,
                    true
                )
                    ? (int) round($median)
                    : round($median, $precision);
            }

            if ($changes !== []) {
                $updates[] = [
                    'id' => (int) $barangay->id,
                    'values' => $changes,
                ];
            }
        }

        $updated = DB::transaction(function () use ($updates): int {
            $count = 0;

            foreach ($updates as $update) {
                $barangay = Barangay::query()
                    ->lockForUpdate()
                    ->findOrFail($update['id']);

                $changes = [];

                foreach ($update['values'] as $field => $value) {
                    // Preserve any existing or concurrently populated values.
                    if ($this->isMissing($barangay->{$field})) {
                        $changes[$field] = $value;
                    }
                }

                if ($changes === []) {
                    continue;
                }

                $changes['updated_at'] = now();

                DB::table('barangays')
                    ->where('id', (int) $barangay->id)
                    ->update($changes);

                $count++;
            }

            return $count;
        });

        $this->command?->info(
            "Updated missing predictors for {$updated} barangays."
        );

        if ($unmatched !== []) {
            $this->command?->warn(
                'Unmatched CSV barangays: '.implode(', ', $unmatched)
            );
        }

        $incomplete = Barangay::query()
            ->active()
            ->get()
            ->filter(function (Barangay $barangay): bool {
                foreach ($this->profileFields() as $field) {
                    if ($this->isMissing($barangay->{$field})) {
                        return true;
                    }
                }

                return false;
            })
            ->pluck('name');

        if ($incomplete->isNotEmpty()) {
            $this->command?->warn(
                'Still missing predictors: '.$incomplete->implode(', ')
            );
        } else {
            $this->command?->info(
                'All active barangays have populated predictor fields.'
            );
        }
    }

    private function profileFields(): array
    {
        return array_merge(
            ['nearest_waterway'],
            array_keys(self::NUMERIC_FIELDS)
        );
    }

    private function readCsv(string $path): Collection
    {
        $file = new SplFileObject($path, 'r');
        $file->setCsvControl(',', '"', '');
        $file->setFlags(
            SplFileObject::READ_CSV
            | SplFileObject::SKIP_EMPTY
            | SplFileObject::DROP_NEW_LINE
        );

        $header = null;
        $records = collect();

        foreach ($file as $index => $row) {
            if (! is_array($row) || $row === [null] || $row === []) {
                continue;
            }

            if ($header === null) {
                $header = array_map(
                    fn ($column) => trim(
                        preg_replace(
                            '/^\xEF\xBB\xBF/',
                            '',
                            (string) $column
                        )
                    ),
                    $row
                );

                $required = array_merge(
                    ['barangay'],
                    $this->profileFields()
                );

                $missing = array_diff($required, $header);

                if ($missing !== []) {
                    throw new RuntimeException(
                        'Missing CSV columns: '.implode(', ', $missing)
                    );
                }

                if (count($header) !== count(array_unique($header))) {
                    throw new RuntimeException(
                        'The CSV contains duplicate column names.'
                    );
                }

                continue;
            }

            if (count($row) !== count($header)) {
                $line = $index + 1;

                throw new RuntimeException(
                    "Unexpected column count near CSV line {$line}."
                );
            }

            $record = array_combine($header, $row);

            $name = trim(
                preg_replace('/\s+/', ' ', (string) $record['barangay'])
            );

            if ($name === '') {
                throw new RuntimeException(
                    'A CSV record has no barangay name.'
                );
            }

            $record['barangay'] = self::ALIASES[$name] ?? $name;
            $records->push($record);
        }

        return $records;
    }

    private function isMissing(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        return is_string($value)
            && in_array(
                strtolower(trim($value)),
                ['', 'null', 'nan', 'n/a', 'unknown'],
                true
            );
    }
}