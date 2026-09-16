<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flood_training_records', function (Blueprint $table): void {
            $table->string('enrichment_status', 30)
                ->default('Pending Enrichment')
                ->after('include_in_training')
                ->index();
            $table->timestamp('enriched_at')->nullable()->after('enrichment_status');
            $table->timestamp('subsided_at')->nullable()->after('enriched_at');
            $table->string('review_status', 30)
                ->default('Pending')
                ->after('subsided_at')
                ->index();
            $table->foreignId('reviewed_by')
                ->nullable()
                ->after('review_status')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->string('model_version', 50)->nullable()->after('reviewed_at');
        });

        DB::table('flood_training_records')
            ->where('include_in_training', true)
            ->update([
                'enrichment_status' => 'Legacy Dataset',
                'review_status' => 'Legacy Approved',
            ]);
    }

    public function down(): void
    {
        Schema::table('flood_training_records', function (Blueprint $table): void {
            $table->dropForeign(['reviewed_by']);
            $table->dropIndex(['enrichment_status']);
            $table->dropIndex(['review_status']);
            $table->dropColumn([
                'enrichment_status',
                'enriched_at',
                'subsided_at',
                'review_status',
                'reviewed_by',
                'reviewed_at',
                'model_version',
            ]);
        });
    }
};
