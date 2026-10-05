<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_incident_reports', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->uuid('submission_token')->unique();
            $table->string('incident_type', 10);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('status', 20)->default('Pending');
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->text('validation_notes')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('fire_incident_id')->nullable()->unique()->constrained('fire_incidents')->restrictOnDelete();
            $table->foreignId('flood_training_record_id')->nullable()->unique()->constrained('flood_training_records')->restrictOnDelete();
            $table->timestamps();
            $table->index(['incident_type', 'status', 'created_at'], 'public_reports_queue_index');
        });

        Schema::create('public_incident_report_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('public_incident_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_incident_report_events');
        Schema::dropIfExists('public_incident_reports');
    }
};
