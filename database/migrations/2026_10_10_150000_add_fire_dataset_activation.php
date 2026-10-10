<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fire_incidents', function (Blueprint $table): void {
            $table->string('source_origin', 30)->default('Staff report');
        });
        Schema::create('fire_dataset_activations', function (Blueprint $table): void {
            $table->string('version')->primary();
            $table->string('source_sha256', 64);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fire_dataset_activations');
        Schema::table('fire_incidents', fn (Blueprint $table) => $table->dropColumn('source_origin'));
    }
};
