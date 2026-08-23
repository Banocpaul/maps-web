<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $role = DB::table('roles')
                ->whereIn('slug', ['operations-officer', 'operations-manager'])
                ->orderByRaw("CASE WHEN slug = 'operations-officer' THEN 0 ELSE 1 END")
                ->first();

            if ($role === null) {
                return;
            }

            DB::table('roles')
                ->where('id', $role->id)
                ->update([
                    'name' => 'Operations Manager',
                    'slug' => 'operations-manager',
                    'description' => 'Manages citywide operational records, predictions, GIS data, communications, and controlled data exports.',
                    'is_active' => true,
                    'updated_at' => now(),
                ]);

            $permissions = [
                ['name' => 'View Operational Records', 'slug' => 'records.view', 'module' => 'records', 'description' => 'Search and review approved operational database records.'],
                ['name' => 'Export Operational Records', 'slug' => 'records.export', 'module' => 'records', 'description' => 'Export filtered operational records in CSV format.'],
                ['name' => 'Manage SMS Automation Rules', 'slug' => 'sms.automation.manage', 'module' => 'sms', 'description' => 'Create, update, enable, disable, and remove SMS automation rules.'],
            ];

            foreach ($permissions as $permission) {
                DB::table('permissions')->updateOrInsert(
                    ['slug' => $permission['slug']],
                    [...$permission, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()]
                );
            }

            $permissionSlugs = [
                'dashboard.view', 'flood.view', 'flood.create', 'flood.edit',
                'flood.delete', 'fire.view', 'fire.create', 'fire.edit',
                'fire.delete', 'hydrants.manage', 'prediction.view',
                'prediction.run', 'prediction.data.manage', 'records.view',
                'records.export', 'gis.view', 'gis.manage', 'sms.view',
                'sms.send', 'sms.recipients.manage', 'sms.automation.manage',
                'reports.view', 'reports.export', 'public-submissions.view',
                'public-submissions.review', 'public-submissions.approve',
                'public-submissions.reject',
            ];

            $permissionIds = DB::table('permissions')
                ->whereIn('slug', $permissionSlugs)
                ->pluck('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $role->id,
                    'permission_id' => $permissionId,
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('roles')
            ->where('slug', 'operations-manager')
            ->update([
                'name' => 'Operations Officer',
                'slug' => 'operations-officer',
                'description' => 'Manages operational incidents, public submissions, GIS, and SMS alerts.',
                'updated_at' => now(),
            ]);
    }
};
