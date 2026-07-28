<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE barangays MODIFY latitude DECIMAL(10,7) NULL");
        DB::statement("ALTER TABLE barangays MODIFY longitude DECIMAL(10,7) NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE barangays MODIFY latitude DECIMAL(10,7) NOT NULL");
        DB::statement("ALTER TABLE barangays MODIFY longitude DECIMAL(10,7) NOT NULL");
    }
};