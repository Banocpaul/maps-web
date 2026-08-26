<?php

namespace Tests\Feature;

use App\Models\DailyWeatherSnapshot;
use App\Services\LiveWeatherService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DailyWeatherSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_open_meteo_is_called_only_once_per_manila_day(): void
    {
        $this->freezeManilaTime();
        Http::fake([
            'https://api.open-meteo.com/v1/forecast*' =>
                Http::response($this->openMeteoResponse(), 200),
        ]);

        $service = app(LiveWeatherService::class);
        $first = $service->getCurrentWeather();
        $second = $service->getCurrentWeather();

        Http::assertSentCount(1);
        $this->assertSame('open-meteo', $first['weather_retrieved_from']);
        $this->assertSame('database', $second['weather_retrieved_from']);
        $this->assertSame('2026-08-26', $second['daily_snapshot_date']);
        $this->assertDatabaseCount('daily_weather_snapshots', 1);

        $snapshot = DailyWeatherSnapshot::query()->sole();
        $this->assertSame(
            '2026-08-26 15:59:59',
            $snapshot->getRawOriginal('expires_at')
        );
    }

    public function test_forced_refresh_replaces_today_snapshot_without_duplicate_row(): void
    {
        $this->freezeManilaTime();
        Http::fake([
            'https://api.open-meteo.com/v1/forecast*' =>
                Http::response($this->openMeteoResponse(), 200),
        ]);

        $service = app(LiveWeatherService::class);
        $service->getCurrentWeather();
        $refreshed = $service->refreshCurrentWeather();

        Http::assertSentCount(2);
        $this->assertSame(
            'open-meteo',
            $refreshed['weather_retrieved_from']
        );
        $this->assertDatabaseCount('daily_weather_snapshots', 1);
    }

    public function test_a_new_manila_day_retrieves_a_new_snapshot(): void
    {
        $this->freezeManilaTime();
        Http::fake([
            'https://api.open-meteo.com/v1/forecast*' =>
                Http::response($this->openMeteoResponse(), 200),
        ]);

        $service = app(LiveWeatherService::class);
        $service->getCurrentWeather();

        Carbon::setTestNow(
            Carbon::create(2026, 8, 27, 0, 5, 0, 'Asia/Manila')
        );
        $service->getCurrentWeather();

        Http::assertSentCount(2);
        $this->assertDatabaseCount('daily_weather_snapshots', 2);
    }

    public function test_daily_refresh_command_reuses_existing_snapshot(): void
    {
        $this->freezeManilaTime();
        Http::fake([
            'https://api.open-meteo.com/v1/forecast*' =>
                Http::response($this->openMeteoResponse(), 200),
        ]);

        $this->artisan('weather:refresh-daily')->assertSuccessful();
        $this->artisan('weather:refresh-daily')->assertSuccessful();

        Http::assertSentCount(1);
        $this->assertDatabaseCount('daily_weather_snapshots', 1);
    }

    private function freezeManilaTime(): void
    {
        Carbon::setTestNow(
            Carbon::create(2026, 8, 26, 8, 0, 0, 'Asia/Manila')
        );
    }

    private function openMeteoResponse(): array
    {
        return [
            'current' => [
                'time' => '2026-08-26T08:00',
                'temperature_2m' => 29.5,
                'relative_humidity_2m' => 82,
                'precipitation' => 1.2,
                'rain' => 1.2,
                'wind_speed_10m' => 4.5,
                'wind_direction_10m' => 225,
                'weather_code' => 61,
            ],
            'hourly' => [
                'time' => [
                    '2026-08-26T07:00',
                    '2026-08-26T08:00',
                    '2026-08-26T09:00',
                    '2026-08-26T10:00',
                ],
                'temperature_2m' => [28.5, 29.5, 30.0, 30.5],
                'relative_humidity_2m' => [85, 82, 80, 78],
                'precipitation' => [0.5, 1.2, 2.0, 0.0],
                'rain' => [0.5, 1.2, 2.0, 0.0],
                'wind_speed_10m' => [4.0, 4.5, 5.0, 5.5],
                'wind_direction_10m' => [220, 225, 230, 235],
                'weather_code' => [61, 61, 63, 3],
            ],
            'daily' => [
                'time' => ['2026-08-26'],
                'temperature_2m_max' => [31.0],
                'temperature_2m_min' => [25.0],
                'precipitation_sum' => [3.7],
                'rain_sum' => [3.7],
            ],
        ];
    }
}
