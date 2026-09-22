<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('flood_analytics_dataset', 'flood_code')) {
            Schema::table('flood_analytics_dataset', function (Blueprint $table): void {
                $table->char('flood_code', 1)->nullable()->index()->after('risk_level');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('flood_analytics_dataset', 'flood_code')) {
            Schema::table('flood_analytics_dataset', function (Blueprint $table): void {
                $table->dropColumn('flood_code');
            });
        }
    }
};
