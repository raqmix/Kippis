<?php

namespace App\Notifications;

/**
 * Danger Notification
 * 
 * Used for errors and security alerts: failed operations, security issues, etc.
 */
class DangerNotification extends DatabaseNotification
{
    public function __construct(
        string $title,
        string $message,
        ?string $url = null,
        array $data = []
    ) {
        $this->type = 'danger';
        $this->title = $title;
        $this->message = $message;
        $this->icon = 'heroicon-o-shield-exclamation';
        $this->url = $url;
        $this->data = $data;
    }
}

