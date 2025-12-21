<?php

namespace App\Core\Policies;

use App\Core\Models\Admin;

class AdminPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->can('manage_admins');
    }

    public function view(Admin $admin, Admin $model): bool
    {
        return $admin->can('manage_admins');
    }

    public function create(Admin $admin): bool
    {
        return $admin->can('manage_admins');
    }

    public function update(Admin $admin, Admin $model): bool
    {
        return $admin->can('manage_admins');
    }

    public function delete(Admin $admin, Admin $model): bool
    {
        return $admin->can('manage_admins');
    }
}

