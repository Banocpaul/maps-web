<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_incident_reports', function (Blueprint $table): void {
            $table->foreignId('incident_barangay_id')->nullable()->constrained('barangays')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('public_incident_reports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('incident_barangay_id');
        });
    }
};
