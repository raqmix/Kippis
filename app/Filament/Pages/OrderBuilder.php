<?php

namespace App\Filament\Pages;

use App\Core\Models\Channel;
use App\Core\Models\Customer;
use App\Core\Models\Order;
use App\Core\Models\Product;
use App\Core\Models\Store;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;

class OrderBuilder extends Page
{
    protected string $view = 'filament.pages.order-builder';

    /** Selected store (required to save). */
    public ?int $storeId = null;

    /** Selected channel (optional). */
    public ?int $channelId = null;

    /** Selected customer (optional). */
    public ?int $customerId = null;

    /** Payment method for the order. */
    public string $paymentMethod = 'cash';

    /**
     * The cart lines. Each line:
     * [
     *   'key'        => string,   // unique row key
     *   'product_id' => int,
     *   'name'       => string,
     *   'unit_price' => float,
     *   'quantity'   => int,
     *   'note'       => string,
     *   'modifiers'  => array<int, array{id:int,name:string,price:float}>, // selected
     *   'available'  => array<int, array{id:int,name:string,price:float}>, // selectable
     * ]
     *
     * @var array<int, array<string, mixed>>
     */
    public array $cart = [];

    /** Product search term. */
    public string $search = '';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-shopping-cart';
    }

    public static function getNavigationLabel(): string
    {
        return __('system.create_order');
    }

    public function getTitle(): string
    {
        return __('system.create_order');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.content_management');
    }

    public static function getNavigationSort(): ?int
    {
        return 0;
    }

    public static function canAccess(): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_orders')
            || Gate::forUser(auth()->guard('admin')->user())->allows('manage_products');
    }

    public function mount(): void
    {
        $this->storeId = Store::query()->value('id');
    }

    /**
     * Products shown in the picker grid (respects the search box).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getProductsProperty(): array
    {
        $query = Product::query()
            ->active()
            ->with('addonModifiers')
            ->orderBy('id');

        $term = trim($this->search);
        if ($term !== '') {
            // name_json is a JSON column; a LIKE over the raw JSON is good enough for a picker.
            $query->whereRaw('LOWER(name_json) LIKE ?', ['%' . strtolower($term) . '%']);
        }

        return $query->limit(60)->get()->map(function (Product $product) {
            return [
                'id'        => $product->id,
                'name'      => $product->getName(app()->getLocale()),
                'price'     => (float) $product->base_price,
                'image'     => $product->image_url,
                'modifiers' => $product->addonModifiers->map(fn ($m) => [
                    'id'    => $m->id,
                    'name'  => $m->getName(app()->getLocale()),
                    'price' => (float) $m->price,
                ])->values()->all(),
            ];
        })->all();
    }

    /** @return array<int, string> */
    public function getStoreOptionsProperty(): array
    {
        return Store::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<int, string> */
    public function getChannelOptionsProperty(): array
    {
        return Channel::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<int, string> */
    public function getCustomerOptionsProperty(): array
    {
        return Customer::query()->orderBy('name')->limit(100)->pluck('name', 'id')->all();
    }

    /**
     * Add a product to the cart (called on drop or click). Increments quantity
     * when the same product (without modifiers) is already present.
     */
    public function addToCart(int $productId): void
    {
        $product = collect($this->products)->firstWhere('id', $productId);
        if (! $product) {
            return;
        }

        foreach ($this->cart as $index => $line) {
            if ($line['product_id'] === $productId && empty($line['modifiers'])) {
                $this->cart[$index]['quantity']++;

                return;
            }
        }

        $this->cart[] = [
            'key'        => uniqid('line_', true),
            'product_id' => $product['id'],
            'name'       => $product['name'],
            'unit_price' => $product['price'],
            'quantity'   => 1,
            'note'       => '',
            'modifiers'  => [],
            'available'  => $product['modifiers'],
        ];
    }

    public function removeLine(string $key): void
    {
        $this->cart = array_values(array_filter($this->cart, fn ($line) => $line['key'] !== $key));
    }

    public function incrementQuantity(string $key): void
    {
        foreach ($this->cart as $index => $line) {
            if ($line['key'] === $key) {
                $this->cart[$index]['quantity']++;

                return;
            }
        }
    }

    public function decrementQuantity(string $key): void
    {
        foreach ($this->cart as $index => $line) {
            if ($line['key'] === $key) {
                if ($this->cart[$index]['quantity'] <= 1) {
                    $this->removeLine($key);

                    return;
                }
                $this->cart[$index]['quantity']--;

                return;
            }
        }
    }

    public function toggleModifier(string $key, int $modifierId): void
    {
        foreach ($this->cart as $index => $line) {
            if ($line['key'] !== $key) {
                continue;
            }

            $selected = collect($line['modifiers']);
            if ($selected->contains('id', $modifierId)) {
                $this->cart[$index]['modifiers'] = $selected
                    ->reject(fn ($m) => $m['id'] === $modifierId)
                    ->values()
                    ->all();

                return;
            }

            $modifier = collect($line['available'])->firstWhere('id', $modifierId);
            if ($modifier) {
                $this->cart[$index]['modifiers'][] = $modifier;
            }

            return;
        }
    }

    /** Unit price including selected modifiers. */
    protected function lineUnitPrice(array $line): float
    {
        $modifiersTotal = collect($line['modifiers'])->sum('price');

        return (float) $line['unit_price'] + (float) $modifiersTotal;
    }

    public function lineTotal(array $line): float
    {
        return $this->lineUnitPrice($line) * (int) $line['quantity'];
    }

    public function getSubtotalProperty(): float
    {
        return collect($this->cart)->sum(fn ($line) => $this->lineTotal($line));
    }

    public function getTaxProperty(): float
    {
        return round($this->subtotal * 0.15, 2);
    }

    public function getTotalProperty(): float
    {
        return round($this->subtotal + $this->tax, 2);
    }

    public function clearCart(): void
    {
        $this->cart = [];
    }

    public function saveOrder(): void
    {
        if (! $this->storeId) {
            Notification::make()->title(__('system.please_select_store'))->danger()->send();

            return;
        }

        if (empty($this->cart)) {
            Notification::make()->title(__('system.cart_is_empty'))->danger()->send();

            return;
        }

        $items = collect($this->cart)->map(fn ($line) => [
            'product_id' => $line['product_id'],
            'name'       => $line['name'],
            'quantity'   => (int) $line['quantity'],
            'price'      => $this->lineUnitPrice($line),
            'note'       => $line['note'] ?? '',
            'modifiers'  => collect($line['modifiers'])->map(fn ($m) => [
                'id'    => $m['id'],
                'name'  => $m['name'],
                'price' => (float) $m['price'],
            ])->values()->all(),
        ])->values()->all();

        $order = Order::create([
            'store_id'           => $this->storeId,
            'customer_id'        => $this->customerId,
            'channel_id'         => $this->channelId,
            'status'             => 'received',
            'subtotal'           => $this->subtotal,
            'tax'                => $this->tax,
            'discount'           => 0,
            'total'              => $this->total,
            'payment_method'     => $this->paymentMethod,
            'pickup_code'        => $this->generatePickupCode(),
            'items_snapshot'     => $items,
            'modifiers_snapshot' => [],
        ]);

        Notification::make()
            ->title(__('system.order_created_successfully'))
            ->body('#' . $order->id . ' · ' . $order->pickup_code)
            ->success()
            ->send();

        $this->cart = [];
        $this->customerId = null;
        $this->channelId = null;
    }

    protected function generatePickupCode(): string
    {
        do {
            $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Order::where('pickup_code', $code)->exists());

        return $code;
    }
}
