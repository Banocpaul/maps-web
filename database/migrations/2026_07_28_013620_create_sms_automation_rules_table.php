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
        Schema::create('sms_automation_rules', function (Blueprint $table): void {
            $table->id();

            // Basic rule information
            $table->string('name');
            $table->text('description')->nullable();

            // Alert category
            $table->enum('hazard_type', [
                'flood',
                'fire',
                'general',
            ])->default('flood');

            /*
            |--------------------------------------------------------------------------
            | Trigger condition
            |--------------------------------------------------------------------------
            |
            | Example:
            | condition_field    = risk_level
            | condition_operator = =
            | condition_value    = High
            |
            */
            $table->string('condition_field', 100);

            $table->enum('condition_operator', [
                '=',
                '!=',
                '>',
                '>=',
                '<',
                '<=',
            ])->default('=');

            $table->string('condition_value', 150);

            // Null means the rule applies to all barangays
            $table->string('barangay', 150)->nullable();

            // Message sent when the condition is satisfied
            $table->text('message_template');

            /*
            |--------------------------------------------------------------------------
            | Recipient filtering
            |--------------------------------------------------------------------------
            |
            | all          = all active recipients
            | flood        = recipients subscribed to flood alerts
            | fire         = recipients subscribed to fire alerts
            | selected     = selected recipients only
            |
            */
            $table->enum('recipient_scope', [
                'all',
                'flood',
                'fire',
                'selected',
            ])->default('all');

            /*
            |--------------------------------------------------------------------------
            | Cooldown
            |--------------------------------------------------------------------------
            |
            | Prevents the same rule from repeatedly sending messages within
            | a short period.
            |
            */
            $table->unsignedInteger('cooldown_minutes')->default(60);

            // Last successful execution of the rule
            $table->timestamp('last_triggered_at')->nullable();

            // Enable or disable the rule
            $table->boolean('is_enabled')->default(true);

            // User who created the rule
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // User who most recently updated the rule
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // Helpful indexes
            $table->index([
                'hazard_type',
                'is_enabled',
            ]);

            $table->index([
                'condition_field',
                'condition_value',
            ]);

            $table->index('barangay');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_automation_rules');
    }
};