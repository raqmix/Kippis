<?php

namespace Database\Seeders;

use App\Core\Models\Admin;
use App\Core\Services\DatabaseNotificationService;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates sample notifications for the super admin to demonstrate
     * the notification system in Filament.
     */
    public function run(): void
    {
        $notificationService = app(DatabaseNotificationService::class);
        
        // Find super admin
        $superAdmin = Admin::whereHas('roles', function ($query) {
            $query->where('name', 'super_admin');
        })->first();

        if (!$superAdmin) {
            $this->command->warn('Super admin not found. Please run AdminSeeder and RolePermissionSeeder first.');
            return;
        }

        $this->command->info('Creating sample notifications for super admin...');

        // Success notification - Record created
        $notificationService->success(
            __('system.created_successfully'),
            __('system.record_has_been_created'),
            $superAdmin,
            null
        );

        // Info notification - Ticket assigned
        $notificationService->info(
            __('system.ticket_assigned'),
            __('system.ticket_assigned_message', [
                'id' => 123,
                'title' => 'Sample Support Ticket'
            ]),
            $superAdmin,
            null
        );

        // Warning notification - Permission change
        $notificationService->warning(
            __('system.security_alert'),
            __('system.role_permissions_modified'),
            $superAdmin,
            null
        );

        // Info notification - Channel updated
        $notificationService->info(
            __('system.channel_updated'),
            __('system.channel_configuration_saved'),
            $superAdmin,
            null
        );

        // Success notification - Settings saved
        $notificationService->success(
            __('system.settings_saved_successfully'),
            __('system.changes_have_been_applied'),
            $superAdmin,
            null
        );

        // Danger notification - Security alert
        $notificationService->danger(
            __('system.failed_login_attempt'),
            __('system.invalid_credentials_attempted', ['email' => 'unknown@example.com']),
            $superAdmin,
            null
        );

        // Info notification - System update
        $notificationService->info(
            'System Update',
            'A new system update is available. Please review the changelog.',
            $superAdmin,
            null
        );

        // Success notification - Backup completed
        $notificationService->success(
            'Backup Completed',
            'Database backup has been completed successfully.',
            $superAdmin,
            null
        );

        $this->command->info('✅ Created 8 sample notifications for super admin!');
        $this->command->info('💡 Login as super admin and check the bell icon in the topbar.');
    }
}

