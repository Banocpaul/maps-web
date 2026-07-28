<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('barangays', 'historical_flood_count_5y')) {
            Schema::table('barangays', function (Blueprint $table): void {
                $table->unsignedInteger('historical_flood_count_5y')
                    ->nullable()
                    ->after('population_density_per_km2');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('barangays', 'historical_flood_count_5y')) {
            Schema::table('barangays', function (Blueprint $table): void {
                $table->dropColumn('historical_flood_count_5y');
            });
        }
    }
};