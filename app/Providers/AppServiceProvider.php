<?php

namespace App\Providers;

use App\Models\DailyWeatherSnapshot;
use App\Services\WeatherObservationRecorder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DailyWeatherSnapshot::saved(function (DailyWeatherSnapshot $snapshot): void {
            try {
                app(WeatherObservationRecorder::class)->record($snapshot);
            } catch (Throwable $error) {
                // Observation storage must not turn a successful forecast fetch into a failure.
                Log::warning('Could not store daily weather observation.', ['snapshot_id' => $snapshot->id, 'message' => $error->getMessage()]);
            }
        });
    }
}
