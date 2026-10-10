<?php

namespace App\Providers;

use App\Models\Barangay;
use App\Models\DailyWeatherSnapshot;
use App\Services\WeatherObservationRecorder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\ViewErrorBag;
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
        RateLimiter::for('public-registration', function (Request $request): Limit {
            return Limit::perMinutes(10, 5)->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    $seconds = max(1, (int) ($headers['Retry-After'] ?? 600));
                    $minutes = (int) ceil($seconds / 60);
                    $message = "Too many account creation attempts. Please try again in {$minutes} minute(s).";

                    if ($request->expectsJson()) {
                        return response()->json(['message' => $message], 429, $headers);
                    }

                    $request->session()->flashInput($request->only([
                        'first_name', 'last_name', 'email', 'contact_number', 'barangay_id',
                        'receive_flood_alerts', 'receive_fire_alerts',
                    ]));

                    return response()->view('public.account.register', [
                        'barangays' => Barangay::active()->orderBy('name')->get(),
                        'errors' => (new ViewErrorBag)->put('default', new MessageBag(['registration' => $message])),
                    ], 429, $headers);
                });
        });

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
