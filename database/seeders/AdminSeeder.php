<?php

namespace Database\Seeders;

use App\Core\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Create Super Admin
        $superAdmin = Admin::firstOrCreate(
            ['email' => 'admin@systemcore.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'is_active' => true,
                'locale' => 'en',
            ]
        );

        $superAdmin->assignRole('super_admin', 'admin');

        // Create additional admin users for testing
        $admin1 = Admin::firstOrCreate(
            ['email' => 'admin@kippis.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'is_active' => true,
                'locale' => 'en',
            ]
        );

        $admin1->assignRole('admin');

        $admin2 = Admin::firstOrCreate(
            ['email' => 'support@kippis.com'],
            [
                'name' => 'Support User',
                'password' => Hash::make('password'),
                'is_active' => true,
                'locale' => 'en',
            ]
        );

        $admin2->assignRole('support');
    }
}
