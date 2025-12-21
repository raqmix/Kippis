<?php

namespace App\Core\Services;

use App\Core\Models\Admin;
use App\Notifications\DangerNotification;
use App\Notifications\InfoNotification;
use App\Notifications\SuccessNotification;
use App\Notifications\WarningNotification;
use Illuminate\Support\Facades\Auth;

/**
 * Database Notification Service
 * 
 * Centralized service for sending Laravel database notifications.
 * All notifications are stored in the database and displayed in Filament topbar.
 * 
 * This service provides a Facebook-like notification experience:
 * - Bell icon with unread count
 * - Dropdown list on click
 * - Unread notifications highlighted
 * - Read notifications faded
 * - Click to mark as read and optionally redirect
 */
class DatabaseNotificationService
{
    /**
     * Send a success notification
     * 
     * @param string $title Short, bold title
     * @param string $message 1 line max message
     * @param Admin|null $admin Target admin (null = current user)
     * @param string|null $url Optional redirect URL
     * @param array $data Additional data
     * @return void
     */
    public function success(
        string $title,
        string $message,
        ?Admin $admin = null,
        ?string $url = null,
        array $data = []
    ): void {
        $admin = $admin ?? Auth::guard('admin')->user();
        
        if ($admin) {
            $admin->notify(new SuccessNotification($title, $message, $url, $data));
        }
    }

    /**
     * Send an info notification
     * 
     * @param string $title Short, bold title
     * @param string $message 1 line max message
     * @param Admin|null $admin Target admin (null = current user)
     * @param string|null $url Optional redirect URL
     * @param array $data Additional data
     * @return void
     */
    public function info(
        string $title,
        string $message,
        ?Admin $admin = null,
        ?string $url = null,
        array $data = []
    ): void {
        $admin = $admin ?? Auth::guard('admin')->user();
        
        if ($admin) {
            $admin->notify(new InfoNotification($title, $message, $url, $data));
        }
    }

    /**
     * Send a warning notification
     * 
     * @param string $title Short, bold title
     * @param string $message 1 line max message
     * @param Admin|null $admin Target admin (null = current user)
     * @param string|null $url Optional redirect URL
     * @param array $data Additional data
     * @return void
     */
    public function warning(
        string $title,
        string $message,
        ?Admin $admin = null,
        ?string $url = null,
        array $data = []
    ): void {
        $admin = $admin ?? Auth::guard('admin')->user();
        
        if ($admin) {
            $admin->notify(new WarningNotification($title, $message, $url, $data));
        }
    }

    /**
     * Send a danger notification
     * 
     * @param string $title Short, bold title
     * @param string $message 1 line max message (generic only, no sensitive data)
     * @param Admin|null $admin Target admin (null = current user)
     * @param string|null $url Optional redirect URL
     * @param array $data Additional data
     * @return void
     */
    public function danger(
        string $title,
        string $message,
        ?Admin $admin = null,
        ?string $url = null,
        array $data = []
    ): void {
        $admin = $admin ?? Auth::guard('admin')->user();
        
        if ($admin) {
            $admin->notify(new DangerNotification($title, $message, $url, $data));
        }
    }

    /**
     * Send notification to multiple admins
     * 
     * @param array $admins Array of Admin models
     * @param string $type Notification type (success, info, warning, danger)
     * @param string $title Short, bold title
     * @param string $message 1 line max message
     * @param string|null $url Optional redirect URL
     * @param array $data Additional data
     * @return void
     */
    public function notifyMultiple(
        array $admins,
        string $type,
        string $title,
        string $message,
        ?string $url = null,
        array $data = []
    ): void {
        foreach ($admins as $admin) {
            match ($type) {
                'success' => $this->success($title, $message, $admin, $url, $data),
                'info' => $this->info($title, $message, $admin, $url, $data),
                'warning' => $this->warning($title, $message, $admin, $url, $data),
                'danger' => $this->danger($title, $message, $admin, $url, $data),
                default => $this->info($title, $message, $admin, $url, $data),
            };
        }
    }

    /**
     * Send notification to admins with specific permission
     * 
     * @param string $permission Permission name
     * @param string $type Notification type (success, info, warning, danger)
     * @param string $title Short, bold title
     * @param string $message 1 line max message
     * @param string|null $url Optional redirect URL
     * @param array $data Additional data
     * @return void
     */
    public function notifyByPermission(
        string $permission,
        string $type,
        string $title,
        string $message,
        ?string $url = null,
        array $data = []
    ): void {
        $admins = Admin::where('is_active', true)
            ->get()
            ->filter(fn ($admin) => $admin->can($permission));

        $this->notifyMultiple($admins->all(), $type, $title, $message, $url, $data);
    }

    /**
     * Mark notification as read
     * 
     * @param \Illuminate\Notifications\DatabaseNotification $notification
     * @return void
     */
    public function markAsRead(\Illuminate\Notifications\DatabaseNotification $notification): void
    {
        if ($notification->read_at === null) {
            $notification->markAsRead();
        }
    }

    /**
     * Mark all notifications as read for an admin
     * 
     * @param Admin $admin
     * @return void
     */
    public function markAllAsRead(Admin $admin): void
    {
        $admin->unreadNotifications->markAsRead();
    }
}

