<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Grant the legacy Operations Officer role permission to run flood predictions.
     */
    public function up(): void
    {
        $roleId = DB::table('roles')
            ->where('slug', 'operations-officer')
            ->value('id');

        $permissionId = DB::table('permissions')
            ->where('slug', 'prediction.run')
            ->value('id');

        if ($roleId === null || $permissionId === null) {
            return;
        }

        DB::table('permission_role')->insertOrIgnore([
            'role_id' => $roleId,
            'permission_id' => $permissionId,
        ]);
    }

    /**
     * Remove only the permission granted by this migration.
     */
    public function down(): void
    {
        $roleId = DB::table('roles')
            ->where('slug', 'operations-officer')
            ->value('id');

        $permissionId = DB::table('permissions')
            ->where('slug', 'prediction.run')
            ->value('id');

        if ($roleId === null || $permissionId === null) {
            return;
        }

        DB::table('permission_role')
            ->where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->delete();
    }
};
