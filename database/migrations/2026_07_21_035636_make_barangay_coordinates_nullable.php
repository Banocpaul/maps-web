<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite is used only for automated tests. The original barangays
        // migration already creates these coordinates as nullable.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement(
            "ALTER TABLE barangays MODIFY latitude DECIMAL(10,7) NULL"
        );

        DB::statement(
            "ALTER TABLE barangays MODIFY longitude DECIMAL(10,7) NULL"
        );
    }

    public function down(): void
    {
        // MySQL/TiDB-specific reversal is not required in SQLite tests.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement(
            "ALTER TABLE barangays MODIFY latitude DECIMAL(10,7) NOT NULL"
        );

        DB::statement(
            "ALTER TABLE barangays MODIFY longitude DECIMAL(10,7) NOT NULL"
        );
    }
};