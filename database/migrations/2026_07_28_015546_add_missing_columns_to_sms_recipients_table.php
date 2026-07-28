<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_recipients', function (Blueprint $table): void {
            if (! Schema::hasColumn('sms_recipients', 'receive_flood_alerts')) {
                $table->boolean('receive_flood_alerts')
                    ->default(true);
            }

            if (! Schema::hasColumn('sms_recipients', 'receive_fire_alerts')) {
                $table->boolean('receive_fire_alerts')
                    ->default(false);
            }

            if (! Schema::hasColumn('sms_recipients', 'receive_general_alerts')) {
                $table->boolean('receive_general_alerts')
                    ->default(true);
            }

            if (! Schema::hasColumn('sms_recipients', 'is_active')) {
                $table->boolean('is_active')
                    ->default(true);
            }

            if (! Schema::hasColumn('sms_recipients', 'created_by')) {
                $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('sms_recipients', 'updated_by')) {
                $table->foreignId('updated_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('sms_recipients', function (Blueprint $table): void {
            if (Schema::hasColumn('sms_recipients', 'updated_by')) {
                $table->dropForeign(['updated_by']);
                $table->dropColumn('updated_by');
            }

            if (Schema::hasColumn('sms_recipients', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }

            $columns = [
                'receive_flood_alerts',
                'receive_fire_alerts',
                'receive_general_alerts',
                'is_active',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('sms_recipients', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};