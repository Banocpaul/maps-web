<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep an index supporting the foreign key before removing its unique index.
        // Guards also allow a retry if MySQL applied only the first DDL statement.
        if (! Schema::hasIndex('public_incident_reports', 'public_reports_fire_link_index')) {
            Schema::table('public_incident_reports', function (Blueprint $table): void {
                $table->index('fire_incident_id', 'public_reports_fire_link_index');
            });
        }
        if (Schema::hasIndex('public_incident_reports', 'public_incident_reports_fire_incident_id_unique')) {
            Schema::table('public_incident_reports', function (Blueprint $table): void {
                $table->dropUnique('public_incident_reports_fire_incident_id_unique');
            });
        }
    }

    public function down(): void
    {
        if (DB::table('public_incident_reports')->whereNotNull('fire_incident_id')
            ->groupBy('fire_incident_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Cannot restore one-report-per-fire constraint while multiple linked reports exist. No reports have been deleted.');
        }
        if (! Schema::hasIndex('public_incident_reports', 'public_incident_reports_fire_incident_id_unique')) {
            Schema::table('public_incident_reports', function (Blueprint $table): void {
                $table->unique('fire_incident_id');
            });
        }
        if (Schema::hasIndex('public_incident_reports', 'public_reports_fire_link_index')) {
            Schema::table('public_incident_reports', function (Blueprint $table): void {
                $table->dropIndex('public_reports_fire_link_index');
            });
        }
    }
};
