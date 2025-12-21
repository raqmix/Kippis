<?php

namespace App\Core\Services;

use App\Core\Enums\NotificationType;
use App\Core\Models\Admin;
use App\Core\Models\AdminNotification;

class NotificationService
{
    /**
     * Create a notification for a specific admin
     */
    public function create(
        Admin $admin,
        NotificationType $type,
        string $title,
        string $message,
        ?array $data = null,
        ?string $actionUrl = null,
        ?string $actionText = null
    ): AdminNotification {
        return AdminNotification::create([
            'admin_id' => $admin->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'action_url' => $actionUrl,
            'action_text' => $actionText,
        ]);
    }

    /**
     * Notify multiple admins
     */
    public function notifyMultiple(
        array $admins,
        NotificationType $type,
        string $title,
        string $message,
        ?array $data = null,
        ?string $actionUrl = null,
        ?string $actionText = null
    ): void {
        foreach ($admins as $admin) {
            $this->create($admin, $type, $title, $message, $data, $actionUrl, $actionText);
        }
    }

    /**
     * Notify all admins with a specific permission
     */
    public function notifyByPermission(
        string $permission,
        NotificationType $type,
        string $title,
        string $message,
        ?array $data = null,
        ?string $actionUrl = null,
        ?string $actionText = null
    ): void {
        $admins = Admin::where('is_active', true)
            ->get()
            ->filter(fn ($admin) => $admin->can($permission));

        $this->notifyMultiple($admins->all(), $type, $title, $message, $data, $actionUrl, $actionText);
    }

    /**
     * Notify when a ticket is assigned
     */
    public function notifyTicketAssigned(Admin $admin, $ticket): void
    {
        // Store in database
        $notification = $this->create(
            $admin,
            NotificationType::TICKET,
            __('system.ticket_assigned'),
            __('system.ticket_assigned_message', ['id' => $ticket->id, 'title' => $ticket->subject ?? 'N/A']),
            ['ticket_id' => $ticket->id],
            \App\Filament\Resources\SupportTicketResource::getUrl('view', ['record' => $ticket]),
            __('system.view_ticket')
        );

        // The notification is stored in our custom table and will be displayed
        // in the Livewire notification bell component
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(AdminNotification $notification): void
    {
        $notification->markAsRead();
    }

    /**
     * Mark all notifications as read for an admin
     */
    public function markAllAsRead(Admin $admin): void
    {
        $admin->unreadNotifications()->update(['read_at' => now()]);
    }
}
