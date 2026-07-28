<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add Business Intelligence fields based on the historical
     * Mandaluyong fire incident dataset.
     */
    public function up(): void
    {
        Schema::table('fire_incidents', function (Blueprint $table) {
            $table->dateTime('occurred_at')
                ->nullable()
                ->after('status');

            $table->dateTime('fire_out_at')
                ->nullable()
                ->after('occurred_at');

            $table->unsignedInteger('duration_minutes')
                ->nullable()
                ->after('fire_out_at');

            $table->unsignedInteger('individuals_affected')
                ->default(0)
                ->after('duration_minutes');

            $table->unsignedInteger('houses_destroyed')
                ->default(0)
                ->after('individuals_affected');

            $table->string('alarm_level', 50)
                ->nullable()
                ->after('houses_destroyed');

            $table->string('data_source', 100)
                ->nullable()
                ->after('alarm_level');

            $table->index('occurred_at');
            $table->index('alarm_level');
            $table->index(['barangay_id', 'occurred_at']);
        });
    }

    /**
     * Remove the Business Intelligence fields.
     */
    public function down(): void
    {
        Schema::table('fire_incidents', function (Blueprint $table) {
            $table->dropIndex(['occurred_at']);
            $table->dropIndex(['alarm_level']);
            $table->dropIndex(['barangay_id', 'occurred_at']);

            $table->dropColumn([
                'occurred_at',
                'fire_out_at',
                'duration_minutes',
                'individuals_affected',
                'houses_destroyed',
                'alarm_level',
                'data_source',
            ]);
        });
    }
};