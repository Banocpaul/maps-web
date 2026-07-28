<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Repair SMS Recipients
        |--------------------------------------------------------------------------
        */

        if (! Schema::hasColumn('sms_recipients', 'full_name')) {
            Schema::table('sms_recipients', function (Blueprint $table): void {
                $table->string('full_name');
            });
        }

        if (! Schema::hasColumn('sms_recipients', 'phone_number')) {
            Schema::table('sms_recipients', function (Blueprint $table): void {
                $table->string('phone_number', 30)->unique();
            });
        }

        if (! Schema::hasColumn('sms_recipients', 'position')) {
            Schema::table('sms_recipients', function (Blueprint $table): void {
                $table->string('position')->nullable();
            });
        }

        if (! Schema::hasColumn('sms_recipients', 'office_or_barangay')) {
            Schema::table('sms_recipients', function (Blueprint $table): void {
                $table->string('office_or_barangay')->nullable();
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Repair SMS Automation Rules
        |--------------------------------------------------------------------------
        */

        if (! Schema::hasColumn('sms_automation_rules', 'name')) {
            Schema::table('sms_automation_rules', function (Blueprint $table): void {
                $table->string('name');
            });
        }

        if (! Schema::hasColumn('sms_automation_rules', 'description')) {
            Schema::table('sms_automation_rules', function (Blueprint $table): void {
                $table->text('description')->nullable();
            });
        }

        if (! Schema::hasColumn('sms_automation_rules', 'hazard_type')) {
            Schema::table('sms_automation_rules', function (Blueprint $table): void {
                $table->string('hazard_type', 50)->default('general');
            });
        }

        if (! Schema::hasColumn('sms_automation_rules', 'condition_field')) {
            Schema::table('sms_automation_rules', function (Blueprint $table): void {
                $table->string('condition_field', 100);
            });
        }

        if (! Schema::hasColumn('sms_automation_rules', 'condition_operator')) {
            Schema::table('sms_automation_rules', function (Blueprint $table): void {
                $table->string('condition_operator', 10)->default('=');
            });
        }

        if (! Schema::hasColumn('sms_automation_rules', 'condition_value')) {
            Schema::table('sms_automation_rules', function (Blueprint $table): void {
                $table->string('condition_value', 150);
            });
        }

        if (! Schema::hasColumn('sms_automation_rules', 'barangay')) {
            Schema::table('sms_automation_rules', function (Blueprint $table): void {
                $table->string('barangay', 150)->nullable();
            });
        }

        if (! Schema::hasColumn('sms_automation_rules', 'message_template')) {
            Schema::table('sms_automation_rules', function (Blueprint $table): void {
                $table->text('message_template');
            });
        }

        if (! Schema::hasColumn('sms_automation_rules', 'recipient_scope')) {
            Schema::table('sms_automation_rules', function (Blueprint $table): void {
                $table->string('recipient_scope', 50)->default('all');
            });
        }

        if (! Schema::hasColumn('sms_automation_rules', 'cooldown_minutes')) {
            Schema::table('sms_automation_rules', function (Blueprint $table): void {
                $table->unsignedInteger('cooldown_minutes')->default(60);
            });
        }

        if (! Schema::hasColumn('sms_automation_rules', 'last_triggered_at')) {
            Schema::table('sms_automation_rules', function (Blueprint $table): void {
                $table->timestamp('last_triggered_at')->nullable();
            });
        }

        if (! Schema::hasColumn('sms_automation_rules', 'is_enabled')) {
            Schema::table('sms_automation_rules', function (Blueprint $table): void {
                $table->boolean('is_enabled')->default(true);
            });
        }

        if (! Schema::hasColumn('sms_automation_rules', 'created_by')) {
            Schema::table('sms_automation_rules', function (Blueprint $table): void {
                $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('sms_automation_rules', 'updated_by')) {
            Schema::table('sms_automation_rules', function (Blueprint $table): void {
                $table->foreignId('updated_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Repair SMS Logs
        |--------------------------------------------------------------------------
        */

        if (! Schema::hasColumn('sms_logs', 'sms_recipient_id')) {
            Schema::table('sms_logs', function (Blueprint $table): void {
                $table->foreignId('sms_recipient_id')
                    ->nullable()
                    ->constrained('sms_recipients')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('sms_logs', 'automation_rule_id')) {
            Schema::table('sms_logs', function (Blueprint $table): void {
                $table->foreignId('automation_rule_id')
                    ->nullable()
                    ->constrained('sms_automation_rules')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('sms_logs', 'sent_by')) {
            Schema::table('sms_logs', function (Blueprint $table): void {
                $table->foreignId('sent_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('sms_logs', 'recipient_name')) {
            Schema::table('sms_logs', function (Blueprint $table): void {
                $table->string('recipient_name')->nullable();
            });
        }

        if (! Schema::hasColumn('sms_logs', 'phone_number')) {
            Schema::table('sms_logs', function (Blueprint $table): void {
                $table->string('phone_number', 30);
            });
        }

        if (! Schema::hasColumn('sms_logs', 'message')) {
            Schema::table('sms_logs', function (Blueprint $table): void {
                $table->text('message');
            });
        }

        if (! Schema::hasColumn('sms_logs', 'source')) {
            Schema::table('sms_logs', function (Blueprint $table): void {
                $table->string('source', 30)->default('manual');
            });
        }

        if (! Schema::hasColumn('sms_logs', 'status')) {
            Schema::table('sms_logs', function (Blueprint $table): void {
                $table->string('status', 30)->default('pending');
            });
        }

        if (! Schema::hasColumn('sms_logs', 'condition_data')) {
            Schema::table('sms_logs', function (Blueprint $table): void {
                $table->json('condition_data')->nullable();
            });
        }

        if (! Schema::hasColumn('sms_logs', 'http_status')) {
            Schema::table('sms_logs', function (Blueprint $table): void {
                $table->unsignedSmallInteger('http_status')->nullable();
            });
        }

        if (! Schema::hasColumn('sms_logs', 'gateway_response')) {
            Schema::table('sms_logs', function (Blueprint $table): void {
                $table->text('gateway_response')->nullable();
            });
        }

        if (! Schema::hasColumn('sms_logs', 'failure_reason')) {
            Schema::table('sms_logs', function (Blueprint $table): void {
                $table->text('failure_reason')->nullable();
            });
        }

        if (! Schema::hasColumn('sms_logs', 'sent_at')) {
            Schema::table('sms_logs', function (Blueprint $table): void {
                $table->timestamp('sent_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Intentionally left empty because this migration repairs existing tables.
    }
};