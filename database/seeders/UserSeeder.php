<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminRole = Role::where('name', Role::ADMIN)->first();
        $inchargeRole = Role::where('name', Role::INCHARGE)->first();
        $userRole = Role::where('name', Role::USER)->first();

        // Create admin user
        User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@email.com',
            'email_verified_at' => now(),
            'password' => '1234',
            'role_id' => $adminRole->id,
        ]);

        // Create incharge user
        User::create([
            'first_name' => 'John',
            'last_name' => 'Support',
            'email' => 'incharge@email.com',
            'email_verified_at' => now(),
            'password' => '1234',
            'role_id' => $inchargeRole->id,
        ]);

        // Create regular user
        User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'user@email.com',
            'email_verified_at' => now(),
            'password' => '1234',
            'role_id' => $userRole->id,
        ]);
    }
}

