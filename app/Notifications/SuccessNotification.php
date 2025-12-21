<?php

namespace App\Notifications;

/**
 * Success Notification
 * 
 * Used for positive outcomes: successful saves, creations, updates, etc.
 */
class SuccessNotification extends DatabaseNotification
{
    public function __construct(
        string $title,
        string $message,
        ?string $url = null,
        array $data = []
    ) {
        $this->type = 'success';
        $this->title = $title;
        $this->message = $message;
        $this->icon = 'heroicon-o-check-circle';
        $this->url = $url;
        $this->data = $data;
    }
}

