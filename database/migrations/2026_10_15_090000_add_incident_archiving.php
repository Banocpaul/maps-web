<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        foreach (['fire_incidents', 'flood_incident_records', 'flood_training_records'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->timestamp('archived_at')->nullable()->index();
                $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('archive_reason', 1000)->nullable();
            });
        }
    }
    public function down(): void {
        foreach (['fire_incidents', 'flood_incident_records', 'flood_training_records'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('archived_by');
                $table->dropColumn(['archived_at', 'archive_reason']);
            });
        }
    }
};
