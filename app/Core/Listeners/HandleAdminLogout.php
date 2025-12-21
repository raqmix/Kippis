<?php

namespace App\Core\Listeners;

use App\Core\Events\AdminLoggedOut;
use App\Core\Models\Admin;
use Illuminate\Auth\Events\Logout;

class HandleAdminLogout
{
    public function handle(Logout $event): void
    {
        // Only handle logout for admin guard
        if ($event->guard === 'admin' && $event->user instanceof Admin) {
            // Fire our custom AdminLoggedOut event
            event(new AdminLoggedOut($event->user));
        }
    }
}

