<?php

use App\Models\Permission;
use App\Models\Role;
use App\Support\RoleAccess;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $roles = Role::whereIn('slug', [...array_keys(RoleAccess::matrix()), 'operations-officer'])->get();
            $slugs = $roles->flatMap(fn (Role $role) => RoleAccess::permissions($role->slug))->unique();
            foreach ($slugs as $slug) {
                Permission::firstOrCreate(['slug' => $slug], [
                    'name' => str($slug)->replace('.', ' ')->headline()->toString(),
                    'module' => explode('.', $slug)[0], 'is_active' => true,
                ]);
            }
            foreach ($roles as $role) {
                $role->permissions()->sync(Permission::whereIn('slug', RoleAccess::permissions($role->slug))->pluck('id'));
                if ($role->slug === 'administrator') {
                    $role->update(['description' => 'Manages accounts, access, security, configuration, and backups. No operational access.']);
                }
            }
        });
    }

    public function down(): void
    {
        // Do not restore the former unrestricted administrator grants on rollback.
    }
};
