<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('flood_analytics_dataset')
            && ! Schema::hasColumn('flood_analytics_dataset', 'deleted_at')) {
            Schema::table('flood_analytics_dataset', fn (Blueprint $table) => $table->softDeletes());
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('flood_analytics_dataset')
            && Schema::hasColumn('flood_analytics_dataset', 'deleted_at')) {
            Schema::table('flood_analytics_dataset', fn (Blueprint $table) => $table->dropSoftDeletes());
        }
    }
};
