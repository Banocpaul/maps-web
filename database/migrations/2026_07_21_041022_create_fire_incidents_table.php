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
        Schema::create('fire_incidents', function (Blueprint $table) {

            $table->id();

            $table->foreignId('barangay_id')
                ->constrained('barangays')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('incident_number')->unique();

            $table->string('incident_type');

            $table->string('location');

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->enum('severity', [
                'Minor',
                'Moderate',
                'Major',
            ]);

            $table->enum('status', [
                'Pending',
                'Responding',
                'Controlled',
                'Resolved',
            ])->default('Pending');

            $table->dateTime('reported_at');

            $table->dateTime('responded_at')->nullable();

            $table->dateTime('resolved_at')->nullable();

            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('severity');
            $table->index('reported_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fire_incidents');
    }
};