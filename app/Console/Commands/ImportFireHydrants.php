<?php

namespace App\Console\Commands;

use App\Models\Barangay;
use App\Models\FireHydrant;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ImportFireHydrants extends Command
{
    /**
     * Usage:
     * php artisan hydrants:import
     * php artisan hydrants:import "C:\path\to\fire_hydrants_complete_77.csv"
     * php artisan hydrants:import --replace
     */
    protected $signature = 'hydrants:import
                            {file=storage/app/imports/fire_hydrants_complete_77.csv : CSV file path}
                            {--replace : Delete all existing hydrants before importing}';

    protected $description = 'Import or update fire hydrants from a CSV file';

    public function handle(): int
    {
        try {
            $filePath = $this->resolveFilePath((string) $this->argument('file'));

            if (! is_file($filePath)) {
                $this->error("CSV file not found: {$filePath}");
                $this->newLine();
                $this->line('Place the CSV at:');
                $this->line(base_path('storage/app/imports/fire_hydrants_complete_77.csv'));

                return self::FAILURE;
            }

            $handle = fopen($filePath, 'rb');

            if ($handle === false) {
                throw new RuntimeException("Unable to open CSV file: {$filePath}");
            }

            $headers = fgetcsv($handle);

            if ($headers === false) {
                fclose($handle);
                throw new RuntimeException('The CSV file is empty.');
            }

            $headers = array_map(function ($header) {
            $header = (string) $header;

    // Remove UTF-8 BOM from the first CSV header.
             $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
             $header = ltrim($header, "\u{FEFF}");

    return Str::snake(trim($header));
}, $headers);

            $requiredHeaders = [
                'barangay',
                'hydrant_code',
                'location',
                'latitude',
                'longitude',
                'status',
            ];

            $missingHeaders = array_values(array_diff($requiredHeaders, $headers));

            if ($missingHeaders !== []) {
                fclose($handle);

                $this->error(
                    'Missing required CSV columns: '.implode(', ', $missingHeaders)
                );

                return self::FAILURE;
            }

            $barangays = Barangay::query()
                ->get(['id', 'name'])
                ->keyBy(fn (Barangay $barangay) => $this->normalizeBarangayName($barangay->name));

            $created = 0;
            $updated = 0;
            $skipped = 0;
            $rowNumber = 1;
            $errors = [];

            DB::beginTransaction();

            if ($this->option('replace')) {
                FireHydrant::query()->delete();
                $this->warn('Existing fire hydrants were removed because --replace was used.');
            }

            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if ($this->isEmptyRow($row)) {
                    continue;
                }

                if (count($row) < count($headers)) {
                    $row = array_pad($row, count($headers), null);
                }

                $row = array_slice($row, 0, count($headers));
                $data = array_combine($headers, $row);

                if ($data === false) {
                    $skipped++;
                    $errors[] = "Row {$rowNumber}: column count does not match the header.";
                    continue;
                }

                try {
                    $barangayName = trim((string) ($data['barangay'] ?? ''));
                    $barangayKey = $this->normalizeBarangayName($barangayName);
                    $barangay = $barangays->get($barangayKey);

                    if (! $barangay) {
                        throw new RuntimeException(
                            "Barangay '{$barangayName}' was not found in the barangays table."
                        );
                    }

                    $hydrantCode = strtoupper(trim((string) ($data['hydrant_code'] ?? '')));

                    if ($hydrantCode === '') {
                        throw new RuntimeException('hydrant_code is required.');
                    }

                    $latitude = $this->parseCoordinate(
                        $data['latitude'] ?? null,
                        'latitude'
                    );

                    $longitude = $this->parseCoordinate(
                        $data['longitude'] ?? null,
                        'longitude'
                    );

                    $status = Str::title(
                        strtolower(trim((string) ($data['status'] ?? 'Active')))
                    );

                    if (! in_array($status, ['Active', 'Inactive', 'Maintenance'], true)) {
                        throw new RuntimeException(
                            "Invalid status '{$status}'. Use Active, Inactive, or Maintenance."
                        );
                    }

                    $attributes = [
                        'barangay_id' => $barangay->id,
                        'location' => trim((string) ($data['location'] ?? '')),
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'status' => $status,
                        'installation_date' => $this->parseDate(
                            $data['installation_date'] ?? null
                        ),
                        'last_inspection_date' => $this->parseDate(
                            $data['last_inspection_date'] ?? null
                        ),
                        'remarks' => $this->nullableText($data['remarks'] ?? null),
                    ];

                    if ($attributes['location'] === '') {
                        throw new RuntimeException('location is required.');
                    }

                    $hydrant = FireHydrant::query()->where('hydrant_code', $hydrantCode)->first();

                    if ($hydrant) {
                        $hydrant->update($attributes);
                        $updated++;
                    } else {
                        FireHydrant::query()->create([
                            'hydrant_code' => $hydrantCode,
                            ...$attributes,
                        ]);
                        $created++;
                    }
                } catch (Throwable $exception) {
                    $skipped++;
                    $errors[] = "Row {$rowNumber}: {$exception->getMessage()}";
                }
            }

            fclose($handle);
            DB::commit();

            $this->newLine();
            $this->info('Fire hydrant import completed.');
            $this->table(
                ['Result', 'Count'],
                [
                    ['Created', $created],
                    ['Updated', $updated],
                    ['Skipped', $skipped],
                    ['Total processed', $created + $updated + $skipped],
                ]
            );

            if ($errors !== []) {
                $this->newLine();
                $this->warn('Rows that were skipped:');

                foreach ($errors as $error) {
                    $this->line("- {$error}");
                }
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function resolveFilePath(string $file): string
    {
        $file = trim($file, "\"' ");

        if ($file === '') {
            return base_path('storage/app/imports/fire_hydrants_complete_77.csv');
        }

        if (
            Str::startsWith($file, ['/'])
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $file) === 1
        ) {
            return $file;
        }

        return base_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file));
    }

    private function normalizeBarangayName(?string $name): string
    {
        $normalized = Str::of((string) $name)
            ->lower()
            ->replace(['ñ', '–', '—'], ['n', '-', '-'])
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->value();

        $aliases = [
            'hagdang bato itaas' => 'hagdan bato itaas',
            'hagdang bato libis' => 'hagdan bato libis',
            'new zaniga' => 'new zaniga',
            'pag asa' => 'pag asa',
            'wack wack greenhills east' => 'wack wack greenhills',
        ];

        return $aliases[$normalized] ?? $normalized;
    }

    private function parseCoordinate(mixed $value, string $field): float
    {
        $value = trim((string) $value);

        if ($value === '' || ! is_numeric($value)) {
            throw new RuntimeException("{$field} must be a decimal number.");
        }

        $coordinate = (float) $value;

        if ($field === 'latitude' && ($coordinate < -90 || $coordinate > 90)) {
            throw new RuntimeException('latitude must be between -90 and 90.');
        }

        if ($field === 'longitude' && ($coordinate < -180 || $coordinate > 180)) {
            throw new RuntimeException('longitude must be between -180 and 180.');
        }

        return $coordinate;
    }

    private function parseDate(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (Throwable) {
            throw new RuntimeException(
                "Invalid date '{$value}'. Use YYYY-MM-DD."
            );
        }
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}