<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fire_incidents', function (Blueprint $table): void {
            $table->unsignedBigInteger('barangay_id')->nullable()->change();
            $table->enum('severity', ['Minor', 'Moderate', 'Major'])->nullable()->change();
            $table->unsignedInteger('individuals_affected')->nullable()->default(null)->change();
            $table->unsignedInteger('houses_destroyed')->nullable()->default(null)->change();
            $table->string('record_classification', 20)->default('Reported')->index();
            $table->string('source_barangay')->nullable();
            $table->string('coordinate_accuracy', 30)->nullable();
            $table->string('cause')->nullable();
            $table->string('alarm_reference', 50)->nullable();
            $table->string('cause_reference')->nullable();
            $table->json('source_record')->nullable();
        });
    }

    public function down(): void
    {
        // Retain nullable legacy fields: an unresolved source barangay cannot be guessed.
        Schema::table('fire_incidents', function (Blueprint $table): void {
            $table->dropColumn(['record_classification', 'source_barangay', 'coordinate_accuracy',
                'cause', 'alarm_reference', 'cause_reference', 'source_record']);
        });
    }
};
