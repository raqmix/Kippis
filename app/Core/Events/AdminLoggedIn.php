<?php

namespace App\Core\Events;

use App\Core\Models\Admin;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AdminLoggedIn
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Admin $admin,
        public string $ipAddress,
        public ?string $userAgent = null
    ) {
    }
}

