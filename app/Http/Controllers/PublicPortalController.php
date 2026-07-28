<?php

namespace App\Http\Controllers;

use App\Services\LiveWeatherService;
use Illuminate\View\View;
use Throwable;

class PublicPortalController extends Controller
{
    public function __construct(
        private readonly LiveWeatherService $liveWeatherService
    ) {
    }

    public function index(): View
    {
        $weather = null;
        $weatherError = null;

        try {
            $weather = $this->liveWeatherService->getCurrentWeather();
        } catch (Throwable $exception) {
            report($exception);

            $weatherError = 'Live weather data is temporarily unavailable.';
        }

        return view('public.index', [
            'weather' => $weather,
            'weatherError' => $weatherError,
        ]);
    }
}