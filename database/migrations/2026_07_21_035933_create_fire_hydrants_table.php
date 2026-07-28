<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the fire hydrants table.
     */
    public function up(): void
    {
        Schema::create('fire_hydrants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('barangay_id')
                ->constrained('barangays')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('hydrant_code')->unique();

            $table->string('location');

            // Nullable until the exact GIS coordinates are verified.
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->enum('status', [
                'Active',
                'Inactive',
                'Maintenance',
            ])->default('Active');

            $table->date('installation_date')->nullable();
            $table->date('last_inspection_date')->nullable();

            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index(['barangay_id', 'status']);
        });
    }

    /**
     * Remove the fire hydrants table.
     */
    public function down(): void
    {
        Schema::dropIfExists('fire_hydrants');
    }
};