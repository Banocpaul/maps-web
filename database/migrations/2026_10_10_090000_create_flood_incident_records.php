<?php

use App\Services\FloodIncidentRecordImporter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('flood_incident_records')) {
            Schema::create('flood_incident_records', function (Blueprint $table): void {
                $table->id();
                $table->string('event_id', 50)->index();
                // The supplied spreadsheet dates are Philippine local time.
                $table->dateTime('observation_datetime');
                $table->dateTime('flood_start_datetime');
                $table->dateTime('flood_subsided_datetime')->nullable();
                $table->date('event_date')->index();
                $table->string('status', 20)->index();
                $table->string('barangay', 100)->index();
                $table->string('flood_code', 1)->index();
                $table->string('nearest_waterway', 150)->nullable();
                foreach (['duration_hours', 'latitude', 'longitude', 'elevation_m', 'distance_to_waterway_m', 'rainfall_24h_mm', 'rainfall_3d_mm', 'rainfall_7d_mm', 'temperature_c', 'temp_max_c', 'temp_min_c', 'wind_speed_kph', 'wind_direction_deg'] as $column) {
                    $table->double($column)->nullable();
                }
                $table->unsignedTinyInteger('storm_signal')->nullable();
                $table->unsignedSmallInteger('year');
                $table->unsignedTinyInteger('month');
                $table->string('day_of_week', 10);
                $table->string('source_file_hash', 64)->nullable();
                $table->unsignedInteger('source_row')->nullable();
                $table->unique(['source_file_hash', 'source_row'], 'flood_incident_source_row_unique');
                $table->timestamps();
                $table->softDeletes();
            });
        }
        app(FloodIncidentRecordImporter::class)->import(database_path('data/flood-incident-records.csv.gz'));
    }

    public function down(): void
    {
        Schema::dropIfExists('flood_incident_records');
    }
};
