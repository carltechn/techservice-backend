<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => Role::ADMIN,
                'display_name' => 'Administrator',
                'description' => 'Full system access. Can manage users, tickets, and system settings.',
            ],
            [
                'name' => Role::INCHARGE,
                'display_name' => 'In-Charge',
                'description' => 'Technical support staff. Can handle and resolve tickets.',
            ],
            [
                'name' => Role::USER,
                'display_name' => 'User',
                'description' => 'Regular user. Can create tickets and communicate with support.',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['name' => $role['name']],
                $role
            );
        }
    }
}

