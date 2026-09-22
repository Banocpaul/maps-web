<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportMdrmmoFloodAnalytics extends Command
{
    protected $signature = 'floods:import-mdrmmo
        {path : Full path to MDRRMO-DATASET.csv}
        {--replace : Back up and replace the current analytics dataset}';

    protected $description = 'Import MDRRMO flood records into flood_analytics_dataset';

    public function handle(): int
    {
        if (! $this->option('replace')) {
            $this->error('Nothing was changed. Re-run with --replace after confirming the CSV path.');
            return self::FAILURE;
        }

        $path = $this->argument('path');
        if (! is_file($path) || ! is_readable($path)) {
            $this->error('CSV file not found or unreadable: ' . $path);
            return self::FAILURE;
        }

        $columns = Schema::getColumnListing('flood_analytics_dataset');
        if ($columns === []) {
            $this->error('Table flood_analytics_dataset was not found.');
            return self::FAILURE;
        }

        $backup = 'flood_analytics_backup_' . now()->format('Ymd_His');
        DB::statement('CREATE TABLE `' . $backup . '` LIKE `flood_analytics_dataset`');
        DB::statement('INSERT INTO `' . $backup . '` SELECT * FROM `flood_analytics_dataset`');

        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle);
        if ($headers === false) {
            $this->error('The CSV has no header row.');
            return self::FAILURE;
        }

        $headers = array_map(fn ($value) => trim((string) $value), $headers);
        $batch = [];
        $imported = 0;
        DB::table('flood_analytics_dataset')->delete();

        while (($values = fgetcsv($handle)) !== false) {
            $source = array_combine($headers, array_pad($values, count($headers), null));
            if (! is_array($source) || empty($source['RECORD_ID'])) {
                continue;
            }

            $event = Carbon::parse($source['EVENT_DATETIME'] ?? ($source['DATE'] . ' ' . $source['TIME']));
            $code = strtoupper(trim((string) ($source['FLOOD CODE'] ?? 'A')));
            $depth = ['A' => 304.8, 'B' => 609.6, 'C' => 914.4, 'D' => 1219.2][$code] ?? 304.8;
            $duration = $this->durationHours($source, $event);
            $row = [
                'event_id' => $source['RECORD_ID'],
                'event_date' => $event->toDateString(),
                'barangay' => $source['BARANGAY'] ?? 'Unknown',
                'nearest_waterway' => 'Unknown',
                'temperature_c' => $source['TEMPERATURE_C'] ?? null,
                'humidity_pct' => $source['HUMIDITY_PCT'] ?? null,
                'rainfall_24h_mm' => $source['RAINFALL_24H_MM'] ?? null,
                'rainfall_3d_mm' => $source['RAINFALL_3D_MM'] ?? null,
                'rainfall_7d_mm' => $source['RAINFALL_7D_MM'] ?? null,
                'wind_speed_kph' => $source['WIND_SPEED_KPH'] ?? null,
                'flood_depth_mm' => $depth,
                'duration_hours' => $duration,
                'risk_level' => in_array($code, ['C', 'D'], true) ? 'High' : ($code === 'B' ? 'Medium' : 'Low'),
                'flood_code' => $code,
                'wet_season' => (int) ($source['WET_SEASON'] ?? 0),
                'storm_signal' => 0,
                'year' => (int) ($source['YEAR'] ?? $event->year),
                'month' => (int) ($source['MONTH'] ?? $event->month),
                'day' => (int) ($source['DAY'] ?? $event->day),
                'hour' => (int) ($source['HOUR'] ?? $event->hour),
                'day_of_week' => (int) ($source['DAY_OF_WEEK'] ?? $event->dayOfWeek),
                'created_at' => now(), 'updated_at' => now(),
            ];
            $batch[] = array_intersect_key($row, array_flip($columns));
            if (count($batch) === 500) {
                DB::table('flood_analytics_dataset')->insert($batch);
                $imported += count($batch);
                $batch = [];
            }
        }
        fclose($handle);
        if ($batch !== []) {
            DB::table('flood_analytics_dataset')->insert($batch);
            $imported += count($batch);
        }
        $this->info('Imported ' . $imported . ' MDRRMO records.');
        $this->info('Backup retained in table: ' . $backup);
        return self::SUCCESS;
    }

    private function durationHours(array $source, Carbon $event): float
    {
        $subside = trim((string) ($source['TIME SUBSIDED'] ?? ''));
        if ($subside === '') return 4.0;
        try {
            $end = Carbon::parse(($source['DATE'] ?? $event->toDateString()) . ' ' . $subside);
            if ($end->lessThanOrEqualTo($event)) $end->addDay();
            return round(max($event->diffInMinutes($end) / 60, 0.25), 2);
        } catch (\Throwable) {
            return 4.0;
        }
    }
}
