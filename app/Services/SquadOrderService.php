<?php

namespace App\Services;

use App\Core\Models\Customer;
use App\Core\Models\Order;
use App\Core\Models\Product;
use App\Core\Models\SquadCartItem;
use App\Core\Models\SquadMember;
use App\Core\Models\SquadSession;
use App\Core\Models\Store;
use App\Events\SquadEvent;
use Illuminate\Support\Str;

class SquadOrderService
{
    private const MAX_MEMBERS = 10;
    private const SESSION_TTL_MINUTES = 30;

    public function createSession(Customer $host, Store $store): SquadSession
    {
        $session = SquadSession::create([
            'host_id'     => $host->id,
            'store_id'    => $store->id,
            'invite_code' => $this->generateInviteCode(),
            'status'      => 'open',
            'expires_at'  => now()->addMinutes(self::SESSION_TTL_MINUTES),
        ]);

        SquadMember::create([
            'squad_session_id' => $session->id,
            'customer_id'      => $host->id,
            'nickname'         => $host->name,
            'joined_at'        => now(),
            'is_host'          => true,
        ]);

        return $session->load(['host', 'store', 'members']);
    }

    public function joinSession(Customer $customer, string $inviteCode): SquadMember
    {
        $session = SquadSession::where('invite_code', $inviteCode)
            ->where('status', 'open')
            ->first();

        if (! $session) {
            throw new \DomainException('Session not found or is no longer open.');
        }

        if ($session->isExpired()) {
            throw new \DomainException('This squad session has expired.');
        }

        if ($session->members()->count() >= self::MAX_MEMBERS) {
            throw new \DomainException('This squad is full (max ' . self::MAX_MEMBERS . ' members).');
        }

        if ($session->members()->where('customer_id', $customer->id)->exists()) {
            throw new \DomainException('You are already in this squad.');
        }

        $member = SquadMember::create([
            'squad_session_id' => $session->id,
            'customer_id'      => $customer->id,
            'nickname'         => $customer->name,
            'joined_at'        => now(),
            'is_host'          => false,
        ]);

        broadcast(new SquadEvent($session->id, 'SquadMemberJoined', [
            'member_id'  => $member->id,
            'nickname'   => $member->nickname,
            'joined_at'  => $member->joined_at->toIso8601String(),
        ]));

        return $member;
    }

    public function addItem(SquadMember $member, array $dto): SquadCartItem
    {
        $session = $member->session;

        if ($session->status !== 'open') {
            throw new \DomainException('Cart is locked or session is closed.');
        }

        if ($session->isExpired()) {
            throw new \DomainException('This squad session has expired.');
        }

        $product = Product::findOrFail($dto['product_id']);
        $unitPrice = (int) ($product->base_price * 100); // convert to piasters

        $item = SquadCartItem::create([
            'squad_session_id' => $session->id,
            'squad_member_id'  => $member->id,
            'product_id'       => $product->id,
            'product_kind'     => $dto['product_kind'] ?? 'standard',
            'quantity'         => $dto['quantity'] ?? 1,
            'modifiers'        => $dto['modifiers'] ?? null,
            'note'             => $dto['note'] ?? null,
            'unit_price'       => $unitPrice,
        ]);

        // Reset TTL on activity
        $session->update(['expires_at' => now()->addMinutes(self::SESSION_TTL_MINUTES)]);

        broadcast(new SquadEvent($session->id, 'SquadCartUpdated', $this->cartPayload($session)));

        return $item;
    }

    public function updateItem(SquadMember $member, SquadCartItem $item, array $dto): SquadCartItem
    {
        if ($item->squad_member_id !== $member->id) {
            throw new \DomainException('You can only edit your own items.');
        }

        $session = $member->session;
        if ($session->status !== 'open') {
            throw new \DomainException('Cart is locked.');
        }

        $item->update(array_filter([
            'quantity'  => $dto['quantity'] ?? $item->quantity,
            'modifiers' => $dto['modifiers'] ?? $item->modifiers,
            'note'      => $dto['note'] ?? $item->note,
        ], fn ($v) => $v !== null));

        broadcast(new SquadEvent($session->id, 'SquadCartUpdated', $this->cartPayload($session)));

        return $item->fresh();
    }

    public function removeItem(SquadMember $member, SquadCartItem $item): void
    {
        // Host can remove any item; others only their own
        if (! $member->is_host && $item->squad_member_id !== $member->id) {
            throw new \DomainException('You can only remove your own items.');
        }

        $sessionId = $item->squad_session_id;
        $item->delete();
        $session = SquadSession::find($sessionId);

        if ($session) {
            broadcast(new SquadEvent($session->id, 'SquadCartUpdated', $this->cartPayload($session)));
        }
    }

    public function lockSession(Customer $host, SquadSession $session): void
    {
        $this->guardIsHost($host, $session);

        if ($session->cartItems()->count() === 0) {
            throw new \DomainException('Cart is empty.');
        }

        $session->update(['status' => 'locked', 'locked_at' => now()]);

        broadcast(new SquadEvent($session->id, 'SquadLocked', [
            'locked_at'  => $session->locked_at->toIso8601String(),
            'locked_by'  => $host->name,
        ]));
    }

    public function checkout(Customer $host, SquadSession $session, array $dto): Order
    {
        $this->guardIsHost($host, $session);

        if ($session->status !== 'locked') {
            throw new \DomainException('Session must be locked before checkout.');
        }

        $items = $session->cartItems()->with('product')->get();

        if ($items->isEmpty()) {
            throw new \DomainException('Cart is empty.');
        }

        // Build items_snapshot in the same format as a regular order
        $itemsSnapshot = $items->map(fn (SquadCartItem $item) => [
            'product_id'        => $item->product_id,
            'name_en'           => $item->product->name_en ?? '',
            'name_ar'           => $item->product->name_ar ?? '',
            'quantity'          => $item->quantity,
            'unit_price'        => $item->unit_price,
            'line_total'        => $item->lineTotal(),
            'modifiers'         => $item->modifiers ?? [],
            'note'              => $item->note,
            'squad_member_id'   => $item->squad_member_id,
            'product_kind'      => $item->product_kind,
        ])->toArray();

        $subtotal = $items->sum(fn ($i) => $i->lineTotal());

        $order = Order::create([
            'customer_id'    => $host->id,
            'store_id'       => $session->store_id,
            'status'         => 'pending',
            'items_snapshot' => $itemsSnapshot,
            'total'          => $subtotal / 100,
            'metadata'       => [
                'squad_session_id' => $session->id,
                'member_count'     => $session->members()->count(),
                'promo_code'       => $dto['promo_code'] ?? null,
            ],
        ]);

        $session->update(['status' => 'checked_out', 'order_id' => $order->id]);

        broadcast(new SquadEvent($session->id, 'SquadCheckedOut', [
            'order_id'      => $order->id,
            'total'         => $subtotal,
        ]));

        return $order;
    }

    public function cancelSession(Customer $host, SquadSession $session): void
    {
        $this->guardIsHost($host, $session);
        $session->update(['status' => 'cancelled']);

        broadcast(new SquadEvent($session->id, 'SquadCancelled', [
            'cancelled_by' => $host->name,
        ]));
    }

    private function guardIsHost(Customer $caller, SquadSession $session): void
    {
        if ($session->host_id !== $caller->id) {
            throw new \DomainException('Only the host can perform this action.');
        }
    }

    private function generateInviteCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (SquadSession::where('invite_code', $code)->exists());

        return $code;
    }

    private function cartPayload(SquadSession $session): array
    {
        $session->load('cartItems.product');
        $items    = $session->cartItems;
        $subtotal = $items->sum(fn ($i) => $i->lineTotal());

        return [
            'items'    => $items->map(fn ($i) => [
                'id'          => $i->id,
                'product_id'  => $i->product_id,
                'name_en'     => $i->product->name_en ?? '',
                'quantity'    => $i->quantity,
                'unit_price'  => $i->unit_price,
                'line_total'  => $i->lineTotal(),
                'member_id'   => $i->squad_member_id,
            ])->toArray(),
            'totals' => [
                'subtotal'   => $subtotal,
                'item_count' => $items->sum('quantity'),
            ],
        ];
    }
}
