<?php

namespace App\Notifications;

/**
 * Warning Notification
 * 
 * Used for attention-required messages: permission warnings, validation issues, etc.
 */
class WarningNotification extends DatabaseNotification
{
    public function __construct(
        string $title,
        string $message,
        ?string $url = null,
        array $data = []
    ) {
        $this->type = 'warning';
        $this->title = $title;
        $this->message = $message;
        $this->icon = 'heroicon-o-exclamation-triangle';
        $this->url = $url;
        $this->data = $data;
    }
}

