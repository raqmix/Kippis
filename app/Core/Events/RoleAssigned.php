<?php

namespace App\Core\Events;

use App\Core\Models\Admin;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoleAssigned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Admin $admin,
        public string $roleName
    ) {
    }
}

