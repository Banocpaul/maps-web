<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // Databases which ran the original August migration still have the
        // SQLite Pending-only constraint. MySQL was already migrated correctly.
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        $definition = DB::scalar("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'fire_incidents'");
        if (!is_string($definition)) {
            throw new RuntimeException('The fire_incidents table is missing.');
        }
        if (str_contains($definition, "'Reported'") && !str_contains($definition, "'Pending'")) {
            return;
        }

        // Allow both values while copying existing rows, then convert them.
        Schema::table('fire_incidents', function (Blueprint $table) {
            $table->enum('severity', ['Minor', 'Moderate', 'Major'])->change();
            $table->enum('status', ['Pending', 'Reported', 'Responding', 'Controlled', 'Resolved'])
                ->default('Reported')->change();
        });
        DB::table('fire_incidents')->where('status', 'Pending')->update(['status' => 'Reported']);
        Schema::table('fire_incidents', function (Blueprint $table) {
            $table->enum('severity', ['Minor', 'Moderate', 'Major'])->change();
            $table->enum('status', ['Reported', 'Responding', 'Controlled', 'Resolved'])
                ->default('Reported')->change();
        });
    }

    public function down(): void
    {
        // The August status migration owns the rollback to Pending.
    }
};
