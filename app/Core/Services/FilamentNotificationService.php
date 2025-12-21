<?php

namespace App\Core\Services;

use App\Core\Models\Admin;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

/**
 * Centralized Filament Notification Service
 * 
 * This service provides a unified interface for sending Filament notifications
 * throughout the application. All notifications use Filament's native notification system.
 * 
 * @package App\Core\Services
 */
class FilamentNotificationService
{
    /**
     * Send a success notification
     * 
     * Auto-dismisses after 5 seconds
     * 
     * @param string $title Notification title
     * @param string|null $body Optional notification body
     * @param Admin|null $admin Target admin (null = current user)
     * @param bool $persist Store in database
     * @return Notification
     */
    public function success(
        string $title,
        ?string $body = null,
        ?Admin $admin = null,
        bool $persist = false
    ): Notification {
        $notification = Notification::make()
            ->title($title)
            ->success()
            ->duration(5000); // Auto-dismiss after 5 seconds

        if ($body) {
            $notification->body($body);
        }

        if ($persist && ($admin ?? Auth::guard('admin')->user())) {
            $notification->sendToDatabase($admin ?? Auth::guard('admin')->user());
        } else {
            $notification->send();
        }

        return $notification;
    }

    /**
     * Send a warning notification
     * 
     * Requires manual dismiss
     * 
     * @param string $title Notification title
     * @param string|null $body Optional notification body
     * @param Admin|null $admin Target admin (null = current user)
     * @param bool $persist Store in database
     * @return Notification
     */
    public function warning(
        string $title,
        ?string $body = null,
        ?Admin $admin = null,
        bool $persist = true
    ): Notification {
        $notification = Notification::make()
            ->title($title)
            ->warning()
            ->persistent(); // Requires manual dismiss

        if ($body) {
            $notification->body($body);
        }

        if ($persist && ($admin ?? Auth::guard('admin')->user())) {
            $notification->sendToDatabase($admin ?? Auth::guard('admin')->user());
        } else {
            $notification->send();
        }

        return $notification;
    }

    /**
     * Send a danger/error notification
     * 
     * Requires manual dismiss
     * 
     * @param string $title Notification title
     * @param string|null $body Optional notification body (generic only, no sensitive data)
     * @param Admin|null $admin Target admin (null = current user)
     * @param bool $persist Store in database
     * @return Notification
     */
    public function danger(
        string $title,
        ?string $body = null,
        ?Admin $admin = null,
        bool $persist = true
    ): Notification {
        $notification = Notification::make()
            ->title($title)
            ->danger()
            ->persistent(); // Requires manual dismiss

        if ($body) {
            $notification->body($body);
        }

        if ($persist && ($admin ?? Auth::guard('admin')->user())) {
            $notification->sendToDatabase($admin ?? Auth::guard('admin')->user());
        } else {
            $notification->send();
        }

        return $notification;
    }

    /**
     * Send an info notification
     * 
     * Auto-dismisses after 5 seconds
     * 
     * @param string $title Notification title
     * @param string|null $body Optional notification body
     * @param Admin|null $admin Target admin (null = current user)
     * @param bool $persist Store in database
     * @return Notification
     */
    public function info(
        string $title,
        ?string $body = null,
        ?Admin $admin = null,
        bool $persist = false
    ): Notification {
        $notification = Notification::make()
            ->title($title)
            ->info()
            ->duration(5000); // Auto-dismiss after 5 seconds

        if ($body) {
            $notification->body($body);
        }

        if ($persist && ($admin ?? Auth::guard('admin')->user())) {
            $notification->sendToDatabase($admin ?? Auth::guard('admin')->user());
        } else {
            $notification->send();
        }

        return $notification;
    }

    /**
     * Send notification to multiple admins
     * 
     * @param array $admins Array of Admin models
     * @param string $type Notification type (success, warning, danger, info)
     * @param string $title Notification title
     * @param string|null $body Optional notification body
     * @param bool $persist Store in database
     * @return void
     */
    public function notifyMultiple(
        array $admins,
        string $type,
        string $title,
        ?string $body = null,
        bool $persist = true
    ): void {
        foreach ($admins as $admin) {
            match ($type) {
                'success' => $this->success($title, $body, $admin, $persist),
                'warning' => $this->warning($title, $body, $admin, $persist),
                'danger' => $this->danger($title, $body, $admin, $persist),
                'info' => $this->info($title, $body, $admin, $persist),
                default => $this->info($title, $body, $admin, $persist),
            };
        }
    }

    /**
     * Send notification to admins with specific permission
     * 
     * @param string $permission Permission name
     * @param string $type Notification type (success, warning, danger, info)
     * @param string $title Notification title
     * @param string|null $body Optional notification body
     * @param bool $persist Store in database
     * @return void
     */
    public function notifyByPermission(
        string $permission,
        string $type,
        string $title,
        ?string $body = null,
        bool $persist = true
    ): void {
        $admins = Admin::where('is_active', true)
            ->get()
            ->filter(fn ($admin) => $admin->can($permission));

        $this->notifyMultiple($admins->all(), $type, $title, $body, $persist);
    }

    /**
     * Send notification with action button
     * 
     * @param string $type Notification type
     * @param string $title Notification title
     * @param string|null $body Optional notification body
     * @param string $actionLabel Action button label
     * @param string $actionUrl Action URL
     * @param Admin|null $admin Target admin
     * @param bool $persist Store in database
     * @return Notification
     */
    public function withAction(
        string $type,
        string $title,
        ?string $body,
        string $actionLabel,
        string $actionUrl,
        ?Admin $admin = null,
        bool $persist = false
    ): Notification {
        $method = match ($type) {
            'success' => 'success',
            'warning' => 'warning',
            'danger' => 'danger',
            'info' => 'info',
            default => 'info',
        };

        $notification = $this->{$method}($title, $body, $admin, $persist);
        
        $notification->actions([
            \Filament\Notifications\Actions\Action::make('view')
                ->label($actionLabel)
                ->url($actionUrl)
                ->button(),
        ]);

        return $notification;
    }
}

