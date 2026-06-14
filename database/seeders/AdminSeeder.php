<?php

namespace Database\Seeders;

use App\Core\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Production seeding requires explicit env credentials so the
        // weak default `password` can't ship to a live super_admin (#36).
        // In non-prod the historical fixtures remain — they're what the
        // local dev / CI flows expect.
        if (app()->environment('production')) {
            $this->seedFromEnv();
            return;
        }

        $this->seedDevFixtures();
    }

    private function seedDevFixtures(): void
    {
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

    private function seedFromEnv(): void
    {
        $email    = env('SUPER_ADMIN_EMAIL');
        $password = env('SUPER_ADMIN_PASSWORD');
        $name     = env('SUPER_ADMIN_NAME', 'Super Admin');

        if (! $email || ! $password) {
            $this->command?->error(
                'AdminSeeder: refusing to seed production without SUPER_ADMIN_EMAIL and SUPER_ADMIN_PASSWORD env vars. '
                . 'Set both, then re-run, or skip AdminSeeder.'
            );
            return;
        }

        if (strlen($password) < 12) {
            $this->command?->error(
                'AdminSeeder: SUPER_ADMIN_PASSWORD must be at least 12 characters in production.'
            );
            return;
        }

        $superAdmin = Admin::firstOrCreate(
            ['email' => $email],
            [
                'name'      => $name,
                'password'  => Hash::make($password),
                'is_active' => true,
                'locale'    => 'en',
            ]
        );

        $superAdmin->assignRole('super_admin', 'admin');
    }
}
