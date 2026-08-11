<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Replace the old fire workflow status "Pending" with "Reported".
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

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

        if ($driver === 'mysql') {
            DB::statement(
                "ALTER TABLE fire_incidents MODIFY COLUMN status "
                . "ENUM('Pending', 'Responding', 'Controlled', 'Resolved') "
                . "NOT NULL DEFAULT 'Pending'"
            );
        }
    }
};
