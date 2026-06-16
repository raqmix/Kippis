<?php

namespace App\Events;

use App\Core\Models\SquadSession;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fires once when the host clicks Checkout on a split-pay squad and
 * payment rows have been created for every member. Triggers the FCM
 * push fan-out via NotifySquadPaymentRequestListener.
 *
 * This is NOT broadcastable — the existing SquadEvent already handles the
 * websocket layer (and is fired alongside this in the service). Keeping
 * them separate lets the listener subscribe by class without filtering
 * SquadEvent names by string.
 */
class SquadCheckoutInitiated
{
    use Dispatchable, SerializesModels;

    public function __construct(public SquadSession $session)
    {
    }
}
