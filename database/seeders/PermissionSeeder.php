<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * All permissions used across Filament resources and pages.
     * Safe to run multiple times (uses firstOrCreate).
     */
    public static array $permissions = [
        // System management
        'manage_admins',
        'manage_roles',
        'manage_settings',

        // Content management
        'manage_pages',
        'manage_channels',
        'manage_payment_methods',
        'manage_notifications',

        // Store & catalog
        'manage_stores',
        'manage_categories',
        'manage_products',
        'manage_modifiers',

        // Commerce
        'manage_orders',
        'manage_customers',
        'manage_promo_codes',
        'manage_loyalty',
        'manage_qr_receipts',

        // Marketing
        'manage_promotions',
        'manage_frames',
        'manage_qr_codes',
        'manage_promo_qr_codes',

        // Support & events
        'manage_support',
        'manage_events',

        // Monitoring / logs
        'view_logs',
        'view_activities',
    ];

    public function run(): void
    {
        // Reset cached permissions
        app()['cache']->forget('spatie.permission.cache');

        foreach (self::$permissions as $permission) {
            Permission::firstOrCreate([
                'name'       => $permission,
                'guard_name' => 'admin',
            ]);
        }

        $this->command->info('Permissions seeded: ' . count(self::$permissions) . ' permissions ensured for guard [admin].');
    }
}
