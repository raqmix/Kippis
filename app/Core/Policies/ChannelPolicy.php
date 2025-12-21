<?php

namespace App\Core\Policies;

use App\Core\Models\Admin;
use App\Core\Models\Channel;

class ChannelPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->can('manage_channels');
    }

    public function view(Admin $admin, Channel $channel): bool
    {
        return $admin->can('manage_channels');
    }

    public function create(Admin $admin): bool
    {
        return $admin->can('manage_channels');
    }

    public function update(Admin $admin, Channel $channel): bool
    {
        return $admin->can('manage_channels');
    }

    public function delete(Admin $admin, Channel $channel): bool
    {
        return $admin->can('manage_channels');
    }
}

