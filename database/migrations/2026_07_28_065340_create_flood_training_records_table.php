<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flood_training_records', function (Blueprint $table): void {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Observation information
            |--------------------------------------------------------------------------
            */

            $table->dateTime('observed_at');
            $table->string('barangay', 100);
            $table->string('data_source', 150)->nullable();
            $table->text('remarks')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Time-based features
            |--------------------------------------------------------------------------
            */

            $table->unsignedTinyInteger('month');
            $table->boolean('is_weekend')->default(false);
            $table->boolean('wet_season')->default(false);
            $table->unsignedTinyInteger('storm_signal')->default(0);

            /*
            |--------------------------------------------------------------------------
            | Barangay and geographic features
            |--------------------------------------------------------------------------
            */

            $table->string('nearest_waterway', 150)->nullable();
            $table->decimal('elevation_m', 8, 2)->default(0);
            $table->decimal('distance_to_waterway_m', 10, 2)->default(0);
            $table->decimal('drainage_index', 8, 4)->default(0);
            $table->decimal('impervious_surface_ratio', 8, 4)->default(0);
            $table->decimal('population_density_per_km2', 12, 2)->default(0);
            $table->unsignedInteger('historical_flood_count_5y')->default(0);

            /*
            |--------------------------------------------------------------------------
            | Weather and environmental features
            |--------------------------------------------------------------------------
            */

            $table->decimal('rainfall_24h_mm', 10, 2)->default(0);
            $table->decimal('rainfall_3d_mm', 10, 2)->default(0);
            $table->decimal('rainfall_7d_mm', 10, 2)->default(0);
            $table->decimal('temperature_c', 6, 2)->default(0);
            $table->decimal('humidity_pct', 6, 2)->default(0);
            $table->decimal('wind_speed_kph', 8, 2)->default(0);
            $table->decimal('tide_level_m', 6, 2)->default(0);

            /*
            |--------------------------------------------------------------------------
            | Verified target values
            |--------------------------------------------------------------------------
            */

            $table->enum('risk_level', [
                'Low',
                'Medium',
                'High',
            ]);

            $table->decimal('flood_depth_mm', 10, 2)->default(0);
            $table->decimal('duration_hours', 8, 2)->default(0);

            /*
            |--------------------------------------------------------------------------
            | Dataset controls
            |--------------------------------------------------------------------------
            */

            $table->boolean('include_in_training')->default(true);
            $table->string('exclusion_reason')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('barangay');
            $table->index('observed_at');
            $table->index('risk_level');
            $table->index('include_in_training');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flood_training_records');
    }
};