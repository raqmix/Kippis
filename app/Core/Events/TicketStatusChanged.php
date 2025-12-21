<?php

namespace App\Core\Events;

use App\Core\Models\SupportTicket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public SupportTicket $ticket,
        public string $oldStatus,
        public string $newStatus
    ) {
    }
}

