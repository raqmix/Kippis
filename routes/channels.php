<?php

use App\Core\Models\Admin;
use App\Core\Models\Customer;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Customer: personal order updates
|--------------------------------------------------------------------------
| Private channel each customer subscribes to for live order status pushes.
*/
Broadcast::channel('orders.{customerId}', function (Customer $customer, int $customerId) {
    return $customer->id === $customerId;
});

/*
|--------------------------------------------------------------------------
| Admin: store order queue
|--------------------------------------------------------------------------
| Private channel per store. Admins may access if they are super-admin OR
| are explicitly assigned to that store (store assignment enforced by
| QueuePolicy once the admin-store relation is implemented in §7).
*/
Broadcast::channel('queue.{storeId}', function (Admin $admin, int $storeId) {
    if ($admin->hasRole('super_admin')) {
        return true;
    }
    // When admin-store pivot is available: $admin->stores()->where('store_id', $storeId)->exists()
    return false;
});

/*
|--------------------------------------------------------------------------
| Presence: squad session (members only)
|--------------------------------------------------------------------------
| Presence channel so each member can see who is in the squad lobby.
*/
Broadcast::channel('squad.{sessionId}', function (Customer $customer, int $sessionId) {
    $member = \App\Core\Models\SquadMember::where('squad_session_id', $sessionId)
        ->where('customer_id', $customer->id)
        ->first();

    if (! $member) {
        return false;
    }

    return [
        'id'       => $customer->id,
        'name'     => $customer->name,
        'is_host'  => $member->is_host,
    ];
});
