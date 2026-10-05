<?php

namespace App\Console\Commands;

use App\Services\LiveWeatherService;
use Illuminate\Console\Command;
use Throwable;

class FetchMapsDailyWeather extends Command
{
    protected $signature = 'maps:weather-fetch';
    protected $description = 'Fetch today\'s Mandaluyong weather, or reuse today\'s successful snapshot';

    public function handle(LiveWeatherService $service): int
    {
        try {
            $weather = $service->getCurrentWeather();
            $today = now('Asia/Manila')->toDateString();
            if (($weather['weather_is_stale'] ?? true)
                || ($weather['daily_snapshot_date'] ?? null) !== $today) {
                $this->error('Today\'s fetch has not succeeded. A previous snapshot is being served; inspect the daily fetch metadata.');
                return self::FAILURE;
            }
            $this->info('Weather ready for ' . $today . ' ('
                . ($weather['weather_retrieved_from'] ?? 'database') . ').');
            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }
}
