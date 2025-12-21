<?php

namespace App\Notifications;

/**
 * Info Notification
 * 
 * Used for informational messages: system updates, general info, etc.
 */
class InfoNotification extends DatabaseNotification
{
    public function __construct(
        string $title,
        string $message,
        ?string $url = null,
        array $data = []
    ) {
        $this->type = 'info';
        $this->title = $title;
        $this->message = $message;
        $this->icon = 'heroicon-o-information-circle';
        $this->url = $url;
        $this->data = $data;
    }
}

