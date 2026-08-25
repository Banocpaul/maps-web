<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $permissions = [
                [
                    'name' => 'View Fire Module',
                    'slug' => 'fire.view',
                    'module' => 'fire',
                    'description' => 'View fire incidents and responder information.',
                ],
                [
                    'name' => 'Create Fire Records',
                    'slug' => 'fire.create',
                    'module' => 'fire',
                    'description' => 'Create official fire records.',
                ],
                [
                    'name' => 'Edit Fire Records',
                    'slug' => 'fire.edit',
                    'module' => 'fire',
                    'description' => 'Edit official fire and response records.',
                ],
                [
                    'name' => 'Delete Fire Records',
                    'slug' => 'fire.delete',
                    'module' => 'fire',
                    'description' => 'Delete official fire records.',
                ],
            ];

            foreach ($permissions as $permission) {
                DB::table('permissions')->updateOrInsert(
                    ['slug' => $permission['slug']],
                    [
                        ...$permission,
                        'is_active' => true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            $roleIds = DB::table('roles')
                ->where(function ($query): void {
                    $query->whereIn('slug', [
                        'operations-manager',
                        'operations-officer',
                    ])->orWhere('name', 'Operations Manager');
                })
                ->pluck('id');

            $permissionIds = DB::table('permissions')
                ->whereIn('slug', [
                    'fire.view',
                    'fire.create',
                    'fire.edit',
                    'fire.delete',
                ])
                ->pluck('id');

            foreach ($roleIds as $roleId) {
                foreach ($permissionIds as $permissionId) {
                    DB::table('permission_role')->insertOrIgnore([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // No automatic removal because some roles may already have
        // legitimately held these permissions before this repair.
    }
};