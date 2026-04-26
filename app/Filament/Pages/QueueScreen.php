<?php

namespace App\Filament\Pages;

use App\Core\Models\Store;
use App\Services\QueueService;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;

class QueueScreen extends Page
{
    protected string $view = 'filament.pages.queue-screen';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-queue-list';
    }

    public static function getNavigationLabel(): string
    {
        return 'Queue Screen';
    }

    public function getTitle(): string
    {
        return 'Order Queue';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Operations';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    /** @var int|null */
    public ?int $selectedStoreId = null;

    /** @var array<string, array> */
    public array $queue = [];

    public function mount(): void
    {
        $stores = Store::query()->orderBy('name_en')->get();
        if ($stores->isNotEmpty()) {
            $this->selectedStoreId = $stores->first()->id;
            $this->loadQueue();
        }
    }

    public function loadQueue(): void
    {
        if (! $this->selectedStoreId) {
            $this->queue = [];
            return;
        }

        $store   = Store::findOrFail($this->selectedStoreId);
        $service = app(QueueService::class);
        $grouped = $service->getStoreQueue($store);

        $this->queue = $grouped->map(fn ($orders) => $orders->map(fn ($order) => [
            'id'              => $order->id,
            'pos_code'        => $order->pos_code,
            'status'          => $order->status,
            'customer_name'   => $order->customer_name ?? 'Guest',
            'item_count'      => is_array($order->items_snapshot) ? count($order->items_snapshot) : 0,
            'elapsed_seconds' => now()->diffInSeconds($order->created_at),
        ])->values()->all())->all();
    }

    public function transition(int $orderId, string $newStatus): void
    {
        $order   = \App\Core\Models\Order::findOrFail($orderId);
        $service = app(QueueService::class);

        try {
            $service->transitionOrder($order, $newStatus, auth('admin')->user());
        } catch (\DomainException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => $e->getMessage()]);
            return;
        }

        $this->loadQueue();
    }

    protected function getFormSchema(): array
    {
        return [
            Select::make('selectedStoreId')
                ->label('Store')
                ->options(Store::query()->orderBy('name_en')->pluck('name_en', 'id'))
                ->reactive()
                ->afterStateUpdated(fn () => $this->loadQueue()),
        ];
    }

    public function getStores(): array
    {
        return Store::query()->orderBy('name_en')->pluck('name_en', 'id')->toArray();
    }
}
