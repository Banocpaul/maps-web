<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_sla_targets', function (Blueprint $table): void {
            $table->id();
            $table->string('hazard', 10)->unique();
            $table->unsignedInteger('validation_minutes')->nullable();
            $table->unsignedInteger('publication_minutes')->nullable();
            $table->unsignedInteger('completion_minutes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::table('flood_training_records', fn (Blueprint $table) => $table->timestamp('handling_completed_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('flood_training_records', fn (Blueprint $table) => $table->dropColumn('handling_completed_at'));
        Schema::dropIfExists('incident_sla_targets');
    }
};
