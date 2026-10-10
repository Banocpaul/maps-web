<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fire_incidents', function (Blueprint $table): void {
            $table->string('record_status', 30)->default('Open')->index();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
        });

        // Preserve the lock on complete closed records from earlier releases.
        DB::table('fire_incidents')->where('status', 'Resolved')
            ->whereNotNull('individuals_affected')->whereNotNull('houses_destroyed')
            ->update(['record_status' => 'Finalized']);
        DB::table('fire_incidents')->where('status', 'Resolved')->where('record_status', 'Open')
            ->update(['record_status' => 'For Assessment']);
    }

    public function down(): void
    {
        Schema::table('fire_incidents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('finalized_by');
            $table->dropIndex(['record_status']);
            $table->dropColumn(['record_status', 'finalized_at']);
        });
    }
};
