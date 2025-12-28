<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()['cache']->forget('spatie.permission.cache');

        // Create permissions
        $permissions = [
            'manage_admins',
            'manage_roles',
            'manage_pages',
            'manage_support',
            'manage_notifications',
            'manage_channels',
            'manage_payment_methods',
            'manage_settings',
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
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }

        // Create roles
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'admin']);
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
        $support = Role::firstOrCreate(['name' => 'support', 'guard_name' => 'admin']);
        $auditor = Role::firstOrCreate(['name' => 'auditor', 'guard_name' => 'admin']);

        // Assign all permissions to super_admin (including manage_customers)
        $superAdmin->givePermissionTo(Permission::all());
        
        // Explicitly ensure manage_customers is assigned (already included in Permission::all(), but explicit for clarity)
        $superAdmin->givePermissionTo('manage_customers');

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
        ]);

        // Assign permissions to support
        $support->givePermissionTo([
            'manage_support',
            'view_logs',
        ]);

        // Assign permissions to auditor
        $auditor->givePermissionTo([
            'view_logs',
            'view_activities',
        ]);
    }
}
