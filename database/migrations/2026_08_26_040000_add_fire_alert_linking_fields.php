<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_recipients', function (Blueprint $table): void {
            $table->foreignId('barangay_id')
                ->nullable()
                ->after('office_or_barangay')
                ->constrained('barangays')
                ->nullOnDelete();

            $table->index(['barangay_id', 'receive_fire_alerts', 'is_active'], 'sms_recipients_fire_alert_lookup');
        });

        DB::table('sms_recipients')
            ->whereNull('barangay_id')
            ->orderBy('id')
            ->eachById(function (object $recipient): void {
                $barangayId = DB::table('barangays')
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $recipient->office_or_barangay))])
                    ->value('id');

                if ($barangayId !== null) {
                    DB::table('sms_recipients')
                        ->where('id', $recipient->id)
                        ->update(['barangay_id' => $barangayId]);
                }
            });

        Schema::table('fire_incidents', function (Blueprint $table): void {
            $table->string('street')->nullable()->after('location');
            $table->string('corner')->nullable()->after('street');
        });

        Schema::table('sms_logs', function (Blueprint $table): void {
            $table->foreignId('fire_incident_id')
                ->nullable()
                ->after('automation_rule_id')
                ->constrained('fire_incidents')
                ->nullOnDelete();
            $table->string('alert_key', 80)->nullable()->after('source');
            $table->unique(
                ['fire_incident_id', 'sms_recipient_id', 'alert_key'],
                'sms_logs_unique_fire_incident_alert'
            );
        });
    }

    public function down(): void
    {
        Schema::table('sms_logs', function (Blueprint $table): void {
            $table->dropUnique('sms_logs_unique_fire_incident_alert');
            $table->dropConstrainedForeignId('fire_incident_id');
            $table->dropColumn('alert_key');
        });

        Schema::table('fire_incidents', function (Blueprint $table): void {
            $table->dropColumn(['street', 'corner']);
        });

        Schema::table('sms_recipients', function (Blueprint $table): void {
            $table->dropIndex('sms_recipients_fire_alert_lookup');
            $table->dropConstrainedForeignId('barangay_id');
        });
    }
};
