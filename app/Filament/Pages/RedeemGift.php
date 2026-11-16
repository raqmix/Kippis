<?php

namespace App\Filament\Pages;

use App\Core\Models\CustomerRedeemWallet;
use App\Services\Rewards\CounterRedemptionService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;

/**
 * Cashier page: type / scan a Rewards-wallet code (or open the QR URL
 * /admin/redeem-gift?code=XXXX from a phone camera), see the gift and
 * mark it used.
 */
class RedeemGift extends Page
{
    protected string $view = 'filament.pages.redeem-gift';

    protected static ?string $slug = 'redeem-gift';

    protected static ?int $navigationSort = 51;

    #[Url(as: 'code')]
    public string $code = '';

    public ?int $entryId = null;

    public bool $searched = false;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-qr-code';
    }

    public static function getNavigationLabel(): string
    {
        return __('rewards.admin.redeem_gift');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('rewards.admin.redeem_gift');
    }

    public static function canAccess(): bool
    {
        $gate = Gate::forUser(auth()->guard('admin')->user());

        return $gate->allows('manage_orders') || $gate->allows('manage_loyalty');
    }

    public function mount(): void
    {
        if ($this->code !== '') {
            $this->lookup();
        }
    }

    public function lookup(): void
    {
        $this->code = CustomerRedeemWallet::normaliseCode($this->code);
        $this->entryId = app(CounterRedemptionService::class)->find($this->code)?->id;
        $this->searched = true;
    }

    public function markUsed(): void
    {
        $entry = $this->entry();
        if (! $entry) {
            return;
        }

        try {
            app(CounterRedemptionService::class)->markUsed($entry, auth()->guard('admin')->user());
            Notification::make()->title(__('rewards.marked_used'))->success()->send();
        } catch (\DomainException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public function entry(): ?CustomerRedeemWallet
    {
        return $this->entryId
            ? CustomerRedeemWallet::with(['customer', 'redeemItem', 'usedByAdmin', 'order'])->find($this->entryId)
            : null;
    }

    public function blockingReason(): ?string
    {
        $entry = $this->entry();

        return $entry ? app(CounterRedemptionService::class)->blockingReason($entry) : null;
    }
}
