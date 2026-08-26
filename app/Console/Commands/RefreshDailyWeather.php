<?php

namespace App\Console\Commands;

use App\Services\LiveWeatherService;
use Illuminate\Console\Command;
use Throwable;

class RefreshDailyWeather extends Command
{
    protected $signature = 'weather:refresh-daily {--force : Replace today\'s saved snapshot}';

    protected $description = 'Retrieve and save the daily Mandaluyong Open-Meteo snapshot';

    public function handle(LiveWeatherService $weatherService): int
    {
        try {
            $weather = $this->option('force')
                ? $weatherService->refreshCurrentWeather()
                : $weatherService->getCurrentWeather();

            $this->info(sprintf(
                'Daily weather snapshot ready for %s (%s).',
                $weather['daily_snapshot_date'] ?? 'today',
                $weather['weather_retrieved_from'] ?? 'unknown source'
            ));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error(
                'Daily weather refresh failed: ' . $exception->getMessage()
            );

            return self::FAILURE;
        }
    }
}
