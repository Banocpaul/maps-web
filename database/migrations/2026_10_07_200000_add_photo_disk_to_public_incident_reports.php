<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_incident_reports', function (Blueprint $table): void {
            $table->string('photo_disk', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('public_incident_reports', function (Blueprint $table): void {
            $table->dropColumn('photo_disk');
        });
    }
};
