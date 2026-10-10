<?php

use App\Models\DailyWeatherSnapshot;
use App\Services\WeatherObservationRecorder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('weather_observations')) {
            Schema::create('weather_observations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('barangay_id')->nullable()->constrained()->nullOnDelete();
                $table->string('station_name')->nullable();
                $table->string('source')->nullable();
                $table->dateTime('observed_at')->index();
                foreach (['rainfall_1h_mm', 'rainfall_24h_mm', 'rainfall_3d_mm', 'rainfall_7d_mm', 'temperature_c', 'relative_humidity_pct', 'wind_speed_kph', 'wind_direction_deg'] as $column) {
                    $table->double($column)->nullable();
                }
                $table->string('weather_condition')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }
        if (! Schema::hasColumn('weather_observations', 'daily_weather_snapshot_id')) {
            Schema::table('weather_observations', function (Blueprint $table): void {
                $table->unsignedBigInteger('daily_weather_snapshot_id')->nullable()->unique();
            });
        }
        DailyWeatherSnapshot::where('source', 'Open-Meteo')->orderBy('id')->chunkById(100, function ($snapshots): void {
            foreach ($snapshots as $snapshot) {
                app(WeatherObservationRecorder::class)->record($snapshot);
            }
        });
    }

    public function down(): void
    {
        // Retain historical observations, including installations with an existing weather table.
        if (Schema::hasColumn('weather_observations', 'daily_weather_snapshot_id')) {
            Schema::table('weather_observations', function (Blueprint $table): void {
                $table->dropUnique(['daily_weather_snapshot_id']);
                $table->dropColumn('daily_weather_snapshot_id');
            });
        }
    }
};
