<?php

namespace App\Http\Controllers;

use App\Services\LiveWeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class WeatherController extends Controller
{
    public function __construct(
        private readonly LiveWeatherService $liveWeatherService
    ) {
    }

    /**
     * Retrieve the latest live weather for Mandaluyong City.
     */
    public function live(): JsonResponse
    {
        try {
            $weather = $this->liveWeatherService->getCurrentWeather();

            return response()->json([
                'success' => true,
                'message' => 'Live weather retrieved successfully.',
                'data' => $weather,
            ]);
        } catch (Throwable $exception) {

            Log::error('Unable to retrieve live weather.', [
                'message' => $exception->getMessage(),
                'exception' => get_class($exception),
            ]);

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 500);
        }
    }
}