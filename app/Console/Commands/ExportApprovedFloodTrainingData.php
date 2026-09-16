<?php

namespace App\Console\Commands;

use App\Models\FloodTrainingRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportApprovedFloodTrainingData extends Command
{
    protected $signature = 'floods:export-training-data
        {--path= : Optional absolute CSV output path}';

    protected $description = 'Export reviewed flood observations approved for model training';

    public function handle(): int
    {
        $records = FloodTrainingRecord::query()
            ->where('include_in_training', true)
            ->where('review_status', 'Approved')
            ->orderBy('observed_at')
            ->get();

        if ($records->isEmpty()) {
            $this->warn('No approved flood observations are ready for export.');

            return self::SUCCESS;
        }

        $path = $this->option('path') ?: storage_path(
            'app/exports/flood-training-approved-'.now()->format('Ymd-His').'.csv'
        );

        File::ensureDirectoryExists(dirname($path));
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            $this->error('Unable to create the training-data export.');

            return self::FAILURE;
        }

        $columns = [
            'observed_at', 'barangay', 'month', 'is_weekend', 'wet_season',
            'storm_signal', 'nearest_waterway', 'elevation_m',
            'distance_to_waterway_m', 'drainage_index',
            'impervious_surface_ratio', 'population_density_per_km2',
            'historical_flood_count_5y', 'rainfall_24h_mm',
            'rainfall_3d_mm', 'rainfall_7d_mm', 'temperature_c',
            'humidity_pct', 'wind_speed_kph', 'tide_level_m', 'risk_level',
            'flood_level_code', 'flood_depth_mm', 'duration_hours',
        ];

        fputcsv($handle, $columns);

        foreach ($records as $record) {
            fputcsv($handle, array_map(
                static fn (string $column) => match ($column) {
                    'observed_at' => $record->observed_at?->toIso8601String(),
                    'is_weekend', 'wet_season' => (int) $record->{$column},
                    default => $record->{$column},
                },
                $columns
            ));
        }

        fclose($handle);

        $this->info("Exported {$records->count()} approved records to {$path}");

        return self::SUCCESS;
    }
}
