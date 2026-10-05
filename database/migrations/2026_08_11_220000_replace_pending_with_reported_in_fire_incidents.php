<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // SQLite rebuilds the table when changing an enum. Its foreign-key PRAGMA
    // must run outside a transaction so linked records survive the rebuild.
    public $withinTransaction = false;

    /**
     * Replace the old fire workflow status "Pending" with "Reported".
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            Schema::table('fire_incidents', function (Blueprint $table) {
                $table->enum('severity', ['Minor', 'Moderate', 'Major'])->change();
                $table->enum('status', ['Pending', 'Reported', 'Responding', 'Controlled', 'Resolved'])
                    ->default('Reported')->change();
            });
        }

        if ($driver === 'mysql') {
            DB::statement(
                "ALTER TABLE fire_incidents MODIFY COLUMN status "
                . "ENUM('Pending', 'Reported', 'Responding', "
                . "'Controlled', 'Resolved') "
                . "NOT NULL DEFAULT 'Reported'"
            );
        }

        DB::table('fire_incidents')
            ->where('status', 'Pending')
            ->update(['status' => 'Reported']);

        if ($driver === 'sqlite') {
            Schema::table('fire_incidents', function (Blueprint $table) {
                $table->enum('severity', ['Minor', 'Moderate', 'Major'])->change();
                $table->enum('status', ['Reported', 'Responding', 'Controlled', 'Resolved'])
                    ->default('Reported')->change();
            });
        }

        if ($driver === 'mysql') {
            DB::statement(
                "ALTER TABLE fire_incidents MODIFY COLUMN status "
                . "ENUM('Reported', 'Responding', 'Controlled', 'Resolved') "
                . "NOT NULL DEFAULT 'Reported'"
            );
        }
    }

    /**
     * Restore the previous fire workflow status when rolling back.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            Schema::table('fire_incidents', function (Blueprint $table) {
                $table->enum('severity', ['Minor', 'Moderate', 'Major'])->change();
                $table->enum('status', ['Pending', 'Reported', 'Responding', 'Controlled', 'Resolved'])
                    ->default('Pending')->change();
            });
        }

        if ($driver === 'mysql') {
            DB::statement(
                "ALTER TABLE fire_incidents MODIFY COLUMN status "
                . "ENUM('Pending', 'Reported', 'Responding', "
                . "'Controlled', 'Resolved') "
                . "NOT NULL DEFAULT 'Pending'"
            );
        }

        DB::table('fire_incidents')
            ->where('status', 'Reported')
            ->update(['status' => 'Pending']);

        if ($driver === 'sqlite') {
            Schema::table('fire_incidents', function (Blueprint $table) {
                $table->enum('severity', ['Minor', 'Moderate', 'Major'])->change();
                $table->enum('status', ['Pending', 'Responding', 'Controlled', 'Resolved'])
                    ->default('Pending')->change();
            });
        }

        if ($driver === 'mysql') {
            DB::statement(
                "ALTER TABLE fire_incidents MODIFY COLUMN status "
                . "ENUM('Pending', 'Responding', 'Controlled', 'Resolved') "
                . "NOT NULL DEFAULT 'Pending'"
            );
        }
    }
};
