<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Seed all permissions first
        $this->call(PermissionSeeder::class);

        // Create roles
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'admin']);
        $admin      = Role::firstOrCreate(['name' => 'admin',       'guard_name' => 'admin']);
        $support    = Role::firstOrCreate(['name' => 'support',     'guard_name' => 'admin']);
        $auditor    = Role::firstOrCreate(['name' => 'auditor',     'guard_name' => 'admin']);

        // super_admin gets every permission
        $superAdmin->syncPermissions(Permission::where('guard_name', 'admin')->pluck('name'));

        // Assign permissions to admin
        $admin->givePermissionTo([
            'manage_pages',
            'manage_support',
            'manage_channels',
            'manage_payment_methods',
            'manage_customers',
            'manage_stores',
            'manage_categories',
            'manage_products',
            'manage_modifiers',
            'manage_promo_codes',
            'manage_orders',
            'manage_loyalty',
            'manage_qr_receipts',
            'view_logs',
            'view_activities',
            'manage_frames',
            'manage_qr_codes',
            'manage_promo_qr_codes',
            'manage_promotions',
            'manage_events',
        ]);

        // Assign permissions to support
        $support->givePermissionTo([
            'manage_support',
            'manage_events',
            'view_logs',
        ]);

        // Assign permissions to auditor
        $auditor->givePermissionTo([
            'view_logs',
            'view_activities',
        ]);
    }
}
