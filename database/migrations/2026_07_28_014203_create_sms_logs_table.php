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
        Schema::create('sms_logs', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('sms_recipient_id')
                ->nullable()
                ->constrained('sms_recipients')
                ->nullOnDelete();

            $table->foreignId('automation_rule_id')
                ->nullable()
                ->constrained('sms_automation_rules')
                ->nullOnDelete();

            $table->foreignId('sent_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('recipient_name')->nullable();
            $table->string('phone_number', 30);

            $table->text('message');

            $table->enum('source', [
                'manual',
                'automatic',
                'test',
            ])->default('manual');

            $table->enum('status', [
                'pending',
                'sent',
                'failed',
            ])->default('pending');

            $table->json('condition_data')->nullable();

            $table->unsignedSmallInteger('http_status')
                ->nullable();

            $table->text('gateway_response')->nullable();
            $table->text('failure_reason')->nullable();

            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['source', 'created_at']);
            $table->index('phone_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};