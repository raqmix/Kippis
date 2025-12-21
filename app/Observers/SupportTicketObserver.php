<?php

namespace App\Observers;

use App\Core\Enums\NotificationType;
use App\Core\Models\SupportTicket;
use App\Core\Services\NotificationService;
use App\Core\Services\DatabaseNotificationService;

class SupportTicketObserver
{
    public function __construct(
        private NotificationService $notificationService,
        private DatabaseNotificationService $dbNotificationService
    ) {
    }

    public function updated(SupportTicket $ticket): void
    {
        // Check if assigned_to was changed
        if ($ticket->wasChanged('assigned_to') && $ticket->assigned_to) {
            $assignedAdmin = $ticket->assignedTo;
            
            if ($assignedAdmin) {
                // Legacy custom notification (can be removed later)
                $this->notificationService->notifyTicketAssigned($assignedAdmin, $ticket);
                
                // Database notification (Filament topbar)
                $this->dbNotificationService->info(
                    __('system.ticket_assigned'),
                    __('system.ticket_assigned_message', [
                        'id' => $ticket->id,
                        'title' => $ticket->subject ?? 'N/A'
                    ]),
                    $assignedAdmin,
                    \App\Filament\Resources\SupportTicketResource::getUrl('view', ['record' => $ticket])
                );
            }
        }
    }
}

