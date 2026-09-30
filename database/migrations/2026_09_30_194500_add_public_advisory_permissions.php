<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('permissions')
            || ! Schema::hasTable('roles')
            || ! Schema::hasTable('permission_role')
        ) {
            return;
        }

        $now = now();

        $permissions = [
            [
                'name' => 'View Public Advisories',
                'slug' => 'advisories.view',
                'module' => 'advisories',
                'description' => 'View and manage the internal public advisory workspace.',
            ],
            [
                'name' => 'Create Public Advisories',
                'slug' => 'advisories.create',
                'module' => 'advisories',
                'description' => 'Publish official public advisories.',
            ],
            [
                'name' => 'Edit Public Advisories',
                'slug' => 'advisories.edit',
                'module' => 'advisories',
                'description' => 'Edit public advisories created by the user.',
            ],
            [
                'name' => 'Delete Public Advisories',
                'slug' => 'advisories.delete',
                'module' => 'advisories',
                'description' => 'Delete public advisories created by the user.',
            ],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $permission['slug']],
                [
                    'name' => $permission['name'],
                    'module' => $permission['module'],
                    'description' => $permission['description'],
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        $permissionIds = DB::table('permissions')
            ->whereIn(
                'slug',
                array_column($permissions, 'slug')
            )
            ->pluck('id');

        $roleIds = DB::table('roles')
            ->whereIn('slug', [
                'operations-manager',
                'flood-analyst',
                'fire-responder',
            ])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->updateOrInsert([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (
            ! Schema::hasTable('permissions')
            || ! Schema::hasTable('permission_role')
        ) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', [
                'advisories.view',
                'advisories.create',
                'advisories.edit',
                'advisories.delete',
            ])
            ->pluck('id');

        DB::table('permission_role')
            ->whereIn('permission_id', $permissionIds)
            ->delete();

        DB::table('permissions')
            ->whereIn('id', $permissionIds)
            ->delete();
    }
};
