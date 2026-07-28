<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('barangays', function (Blueprint $table) {
            $table->id();

            $table->string('name')->unique();
            $table->unsignedTinyInteger('district');

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->decimal('elevation_m', 6, 2)->nullable();

            $table->string('nearest_waterway')->nullable();
            $table->decimal('distance_to_waterway_m', 8, 2)->nullable();

            $table->decimal('drainage_index', 5, 2)->nullable();
            $table->decimal('impervious_surface_ratio', 5, 2)->nullable();

            $table->integer('population_density_per_km2')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('barangays');
    }
};