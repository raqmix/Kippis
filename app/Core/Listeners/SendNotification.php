<?php

namespace App\Core\Listeners;

use App\Core\Events\TicketStatusChanged;
use App\Core\Services\NotificationService;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendNotification
{
    public function __construct(
        private NotificationService $notificationService
    ) {
    }

    public function handle(TicketStatusChanged $event): void
    {
        // Placeholder for notification logic
        // This would send notifications when ticket status changes
    }
}

