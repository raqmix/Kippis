<?php

namespace App\Core\Policies;

use App\Core\Models\Admin;
use App\Core\Models\Page;

class PagePolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->can('manage_pages');
    }

    public function view(Admin $admin, Page $page): bool
    {
        return $admin->can('manage_pages');
    }

    public function create(Admin $admin): bool
    {
        return $admin->can('manage_pages');
    }

    public function update(Admin $admin, Page $page): bool
    {
        return $admin->can('manage_pages');
    }

    public function delete(Admin $admin, Page $page): bool
    {
        return $admin->can('manage_pages');
    }
}

