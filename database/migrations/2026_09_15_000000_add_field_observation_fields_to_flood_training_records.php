<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flood_training_records', function (Blueprint $table): void {
            $table->char('flood_level_code', 1)->nullable()->after('risk_level')->index();
            $table->string('flood_status', 20)->nullable()->after('flood_level_code')->index();
            $table->string('location_name', 255)->nullable()->after('barangay');
            $table->string('geometry_type', 20)->nullable()->after('location_name');
            $table->json('geometry_geojson')->nullable()->after('geometry_type');
            $table->decimal('latitude', 10, 7)->nullable()->after('geometry_geojson');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->decimal('extent_length_m', 12, 2)->nullable()->after('longitude');
            $table->decimal('affected_area_m2', 14, 2)->nullable()->after('extent_length_m');
        });
    }

    public function down(): void
    {
        Schema::table('flood_training_records', function (Blueprint $table): void {
            $table->dropIndex(['flood_level_code']);
            $table->dropIndex(['flood_status']);
            $table->dropColumn(['flood_level_code', 'flood_status', 'location_name', 'geometry_type', 'geometry_geojson', 'latitude', 'longitude', 'extent_length_m', 'affected_area_m2']);
        });
    }
};
