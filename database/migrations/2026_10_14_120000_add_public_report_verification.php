<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        Schema::create('incident_report_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('threshold')->default(10);
            $table->unsignedInteger('radius_m')->default(100);
            $table->unsignedInteger('window_minutes')->default(120);
        });
        DB::table('incident_report_settings')->insert(['id'=>1, 'threshold'=>10, 'radius_m'=>100, 'window_minutes'=>120]);
        Schema::table('public_incident_reports', function (Blueprint $table): void {
            $table->unsignedBigInteger('report_group_id')->nullable()->index();
            $table->boolean('community_verified')->default(false);
        });
    }
    public function down(): void {
        Schema::table('public_incident_reports', fn (Blueprint $table) => $table->dropColumn(['report_group_id', 'community_verified']));
        Schema::dropIfExists('incident_report_settings');
    }
};
