<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_weather_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->date('snapshot_date')->unique();
            $table->string('source')->default('Open-Meteo');
            $table->json('weather_data');
            $table->timestamp('fetched_at');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_weather_snapshots');
    }
};
