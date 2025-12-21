<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

/**
 * Base Database Notification Class
 * 
 * All database notifications extend this class to ensure
 * consistent structure and Facebook-like UX.
 */
abstract class DatabaseNotification extends Notification
{
    use Queueable;

    /**
     * Notification type (success, info, warning, danger)
     */
    protected string $type;

    /**
     * Notification title (short, bold)
     */
    protected string $title;

    /**
     * Notification message (1 line max)
     */
    protected string $message;

    /**
     * Heroicon name
     */
    protected string $icon;

    /**
     * Optional redirect URL
     */
    protected ?string $url = null;

    /**
     * Additional data
     */
    protected array $data = [];

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     * 
     * This structure is used by Filament to display notifications
     * in the topbar with Facebook-like UX.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'icon' => $this->icon,
            'url' => $this->url,
            'data' => $this->data,
            'created_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}

