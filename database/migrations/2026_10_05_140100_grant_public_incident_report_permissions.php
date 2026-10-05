<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roles = DB::table('roles')->whereIn('slug', [
            'operations-manager', 'operations-officer', 'fire-responder', 'flood-analyst',
        ])->pluck('id');

        foreach (['view', 'review', 'approve', 'reject'] as $action) {
            $slug = 'public-submissions.'.$action;
            DB::table('permissions')->updateOrInsert(['slug' => $slug], [
                'name' => ucfirst($action).' Public Incident Reports',
                'module' => 'public-submissions',
                'description' => ucfirst($action).' reports submitted by the public.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $id = DB::table('permissions')->where('slug', $slug)->value('id');
            foreach ($roles as $roleId) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $roleId, 'permission_id' => $id,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Preserve permissions and assignments that existed before this migration.
    }
};
