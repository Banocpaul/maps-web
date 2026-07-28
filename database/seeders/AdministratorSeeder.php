<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdministratorSeeder extends Seeder
{
    public function run(): void
    {
        $administratorRole = Role::where(
            'slug',
            'administrator'
        )->firstOrFail();

        User::updateOrCreate(
            [
                'email' => 'admin@maps.local',
            ],
            [
                'role_id' => $administratorRole->id,
                'name' => 'M.A.P.S. Administrator',
                'first_name' => 'M.A.P.S.',
                'last_name' => 'Administrator',
                'contact_number' => null,
                'password' => 'Admin12345!',
                'is_active' => true,
                'email_verified_at' => now(),
                'approved_at' => now(),
            ]
        );
    }
}