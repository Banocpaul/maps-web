<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->insertOrIgnore([
            'name' => 'Public Resident', 'slug' => 'public-resident',
            'description' => 'Public reporting and personal barangay alert subscriptions only.',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('barangay_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('receive_flood_alerts')->default(false);
            $table->boolean('receive_fire_alerts')->default(false);
        });
        Schema::table('sms_recipients', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->unique()->constrained()->cascadeOnDelete();
        });
        Schema::table('public_incident_reports', function (Blueprint $table): void {
            // Null ownership preserves reports submitted before resident accounts existed.
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reporter_barangay_id')->nullable()->constrained('barangays')->nullOnDelete();
            $table->index(['submitted_by', 'created_at']);
        });
        Schema::table('sms_logs', function (Blueprint $table): void {
            $table->foreignId('flood_training_record_id')->nullable()->constrained()->nullOnDelete();
            $table->unique(['flood_training_record_id', 'sms_recipient_id', 'alert_key'], 'sms_logs_unique_flood_alert');
        });
    }

    public function down(): void
    {
        Schema::table('sms_logs', function (Blueprint $table): void {
            $table->dropUnique('sms_logs_unique_flood_alert');
            $table->dropConstrainedForeignId('flood_training_record_id');
        });
        Schema::table('public_incident_reports', function (Blueprint $table): void {
            $table->dropIndex(['submitted_by', 'created_at']);
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropConstrainedForeignId('reporter_barangay_id');
        });
        Schema::table('sms_recipients', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('barangay_id');
            $table->dropColumn(['receive_flood_alerts', 'receive_fire_alerts']);
        });
        // Keep the role: existing resident accounts must never become staff on rollback.
    }
};
