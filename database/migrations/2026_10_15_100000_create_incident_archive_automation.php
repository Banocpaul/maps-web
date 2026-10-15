<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
return new class extends Migration {
    public function up(): void {
        Schema::create('incident_archive_automation', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->timestamp('initial_completed_at')->nullable();
            $table->string('last_completed_month', 7)->nullable();
            $table->timestamp('last_completed_at')->nullable();
            $table->json('last_result')->nullable();
        });
        DB::table('incident_archive_automation')->insert(['id' => 1]);
    }
    public function down(): void { Schema::dropIfExists('incident_archive_automation'); }
};
