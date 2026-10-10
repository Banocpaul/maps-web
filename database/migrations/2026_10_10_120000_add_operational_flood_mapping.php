<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flood_incident_records', function (Blueprint $table): void {
            $table->json('geometry_geojson')->nullable();
            $table->double('extent_length_m')->nullable();
            foreach (['drainage_index', 'impervious_surface_ratio', 'population_density_per_km2', 'humidity_pct'] as $field) {
                $table->double($field)->nullable();
            }
            $table->unsignedInteger('historical_flood_count_5y')->nullable();
            $table->string('enrichment_status', 30)->nullable();
            $table->string('enrichment_note')->nullable();
            $table->string('weather_source', 50)->nullable();
            $table->dateTime('weather_observed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::create('flood_incident_updates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('flood_incident_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 30);
            $table->string('previous_code', 1);
            $table->string('flood_code', 1);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flood_incident_updates');
        Schema::table('flood_incident_records', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['geometry_geojson', 'extent_length_m', 'drainage_index', 'impervious_surface_ratio', 'population_density_per_km2', 'humidity_pct', 'historical_flood_count_5y', 'enrichment_status', 'enrichment_note', 'weather_source', 'weather_observed_at']);
        });
    }
};
