<?php

namespace App\Core\Policies;

use App\Core\Models\Admin;
use App\Core\Models\PaymentMethod;

class PaymentMethodPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->can('manage_payment_methods');
    }

    public function view(Admin $admin, PaymentMethod $paymentMethod): bool
    {
        return $admin->can('manage_payment_methods');
    }

    public function create(Admin $admin): bool
    {
        return $admin->can('manage_payment_methods');
    }

    public function update(Admin $admin, PaymentMethod $paymentMethod): bool
    {
        return $admin->can('manage_payment_methods');
    }

    public function delete(Admin $admin, PaymentMethod $paymentMethod): bool
    {
        return $admin->can('manage_payment_methods');
    }
}

