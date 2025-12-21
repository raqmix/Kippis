<?php

namespace Tests\Feature;

use App\Core\Models\Admin;
use App\Core\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SettingResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear permission cache
        app()['cache']->forget('spatie.permission.cache');
        
        // Create permissions
        Permission::firstOrCreate(['name' => 'manage_settings', 'guard_name' => 'admin']);
        
        // Create super admin role
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'admin']);
        $superAdmin->givePermissionTo('manage_settings');
    }

    public function test_super_admin_can_access_settings_page(): void
    {
        // Get the role that was created in setUp
        $superAdminRole = Role::where('name', 'super_admin')->where('guard_name', 'admin')->first();
        
        $admin = Admin::factory()->create();
        $admin->syncRoles([$superAdminRole]);
        
        // Clear permission cache
        app()['cache']->forget('spatie.permission.cache');
        
        // Refresh the admin to reload relationships
        $admin = $admin->fresh(['roles', 'permissions']);
        
        // Verify the admin has the permission directly
        $this->assertTrue($admin->hasPermissionTo('manage_settings'), 'Admin should have manage_settings permission');
        
        // Also verify via role
        $this->assertTrue($admin->hasRole('super_admin'), 'Admin should have super_admin role');
        $this->assertTrue($superAdminRole->hasPermissionTo('manage_settings'), 'Role should have manage_settings permission');
        
        // Test the canViewAny method with authenticated user
        $this->actingAs($admin, 'admin');
        $canView = \App\Filament\Resources\SettingResource::canViewAny();
        $this->assertTrue($canView, 'canViewAny should return true for super admin');
    }

    public function test_non_super_admin_cannot_access_settings_page(): void
    {
        $admin = Admin::factory()->create();
        // Don't assign super_admin role

        $response = $this->actingAs($admin, 'admin')
            ->get('/admin/settings');

        $response->assertForbidden();
    }

    public function test_can_save_settings(): void
    {
        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');

        // Test setting individual values
        Setting::set('phone', '+1234567890', 'string', 'contact');
        Setting::set('email', 'contact@example.com', 'string', 'contact');
        Setting::set('app_name', 'Test App', 'string', 'application');
        Setting::set('working_application', true, 'boolean', 'application');

        // Verify settings are saved
        $this->assertEquals('+1234567890', Setting::get('phone'));
        $this->assertEquals('contact@example.com', Setting::get('email'));
        $this->assertEquals('Test App', Setting::get('app_name'));
        $this->assertTrue(Setting::get('working_application'));
    }

    public function test_settings_are_grouped_correctly(): void
    {
        Setting::set('phone', '+1234567890', 'string', 'contact');
        Setting::set('app_name', 'Test App', 'string', 'application');
        Setting::set('facebook', 'https://facebook.com', 'string', 'social');

        $this->assertDatabaseHas('settings', [
            'key' => 'phone',
            'group' => 'contact',
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'app_name',
            'group' => 'application',
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'facebook',
            'group' => 'social',
        ]);
    }
}
