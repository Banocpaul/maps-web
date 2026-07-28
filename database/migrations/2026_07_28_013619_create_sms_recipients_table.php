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
        Schema::create('sms_recipients', function (Blueprint $table): void {
            $table->id();

            $table->string('full_name');
            $table->string('phone_number', 30)->unique();

            $table->string('position')->nullable();
            $table->string('office_or_barangay')->nullable();

            $table->boolean('receive_flood_alerts')
                ->default(true);

            $table->boolean('receive_fire_alerts')
                ->default(false);

            $table->boolean('receive_general_alerts')
                ->default(true);

            $table->boolean('is_active')
                ->default(true);

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('full_name');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_recipients');
    }
};