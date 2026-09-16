<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Core\Models\Product;
use App\Filament\Resources\OrderResource;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function mount(int | string $record): void
    {
        parent::mount($record);

        // Load all necessary relationships
        $this->record->load(['store', 'customer', 'promoCode', 'paymentMethod', 'refunds.admin']);
    }

    /**
     * Void is only valid while the order has not been fulfilled and no
     * prior refund/void has been issued. Mirrors RefundService::void().
     */
    private function canVoid(): bool
    {
        return in_array($this->record->status, ['received', 'pending_payment'], true)
            && $this->record->refund_status === 'none';
    }

    /**
     * Refund is only valid for completed orders that haven't been fully
     * refunded yet. Mirrors RefundService::refundFull/Partial guards.
     */
    private function canRefund(): bool
    {
        return $this->record->status === 'completed'
            && $this->record->refund_status !== 'full';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download_pdf')
                ->label(__('system.download_receipt'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->url(fn () => route('admin.orders.download-pdf', $this->record->id))
                ->openUrlInNewTab(),
            Actions\Action::make('update_status')
                ->label(__('system.update_status'))
                ->icon('heroicon-o-arrow-path')
                ->visible(fn () => \Illuminate\Support\Facades\Gate::forUser(auth()->guard('admin')->user())->allows('manage_orders'))
                ->form([
                    \Filament\Forms\Components\Select::make('status')
                        ->label(__('system.status'))
                        ->options([
                            'received' => __('system.received'),
                            'mixing' => __('system.mixing'),
                            'ready' => __('system.ready'),
                            'completed' => __('system.completed'),
                            'cancelled' => __('system.cancelled'),
                        ])
                        ->required(),
                ])
                ->action(function (array $data) {
                    $this->record->update(['status' => $data['status']]);
                    \Filament\Notifications\Notification::make()
                        ->title(__('system.status_updated'))
                        ->success()
                        ->send();
                }),

            Actions\Action::make('void_order')
                ->label(__('system.void_order'))
                ->icon('heroicon-o-no-symbol')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('system.void_order'))
                ->modalDescription(__('system.void_order_description'))
                ->visible(fn () => $this->canVoid()
                    && \Illuminate\Support\Facades\Gate::forUser(auth()->guard('admin')->user())->allows('manage_orders'))
                ->form([
                    \Filament\Forms\Components\Textarea::make('reason')
                        ->label(__('system.reason'))
                        ->required()
                        ->maxLength(500)
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    try {
                        $refund = app(\App\Services\RefundService::class)
                            ->void($this->record, auth('admin')->user(), $data['reason']);
                    } catch (\DomainException $e) {
                        \Filament\Notifications\Notification::make()
                            ->title(__('system.refund_not_allowed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                        return;
                    }

                    if ($refund->status === 'failed') {
                        \Filament\Notifications\Notification::make()
                            ->title(__('system.gateway_void_failed'))
                            ->body(__('system.gateway_void_failed_body'))
                            ->warning()
                            ->persistent()
                            ->send();
                    } else {
                        \Filament\Notifications\Notification::make()
                            ->title(__('system.order_voided'))
                            ->success()
                            ->send();
                    }

                    $this->record->refresh();
                    $this->record->load(['refunds.admin']);
                }),

            Actions\Action::make('refund_order')
                ->label(__('system.refund_order'))
                ->icon('heroicon-o-banknotes')
                ->color('danger')
                ->visible(fn () => $this->canRefund()
                    && \Illuminate\Support\Facades\Gate::forUser(auth()->guard('admin')->user())->allows('manage_orders'))
                ->form(function () {
                    $totalPiasters = \App\Support\Money::toPiasters((float) $this->record->total);
                    $refunded      = (int) ($this->record->refunded_amount ?? 0);
                    $availableEgp  = number_format(($totalPiasters - $refunded) / 100, 2, '.', '');

                    return [
                        \Filament\Forms\Components\Select::make('type')
                            ->label(__('system.refund_type'))
                            ->options([
                                'full' => __('system.full_refund'),
                                'partial' => __('system.partial_refund'),
                            ])
                            ->default('full')
                            ->live()
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('amount_egp')
                            ->label(__('system.amount_egp', ['available' => $availableEgp]))
                            ->numeric()
                            ->minValue(0.01)
                            ->maxValue((float) $availableEgp)
                            ->step(0.01)
                            ->visible(fn ($get) => $get('type') === 'partial')
                            ->required(fn ($get) => $get('type') === 'partial'),
                        \Filament\Forms\Components\Textarea::make('reason')
                            ->label(__('system.reason'))
                            ->required()
                            ->maxLength(500)
                            ->rows(3),
                    ];
                })
                ->action(function (array $data) {
                    $svc   = app(\App\Services\RefundService::class);
                    $admin = auth('admin')->user();

                    try {
                        if ($data['type'] === 'full') {
                            $refund = $svc->refundFull($this->record, $admin, $data['reason']);
                        } else {
                            $piasters = (int) round(((float) $data['amount_egp']) * 100);
                            $refund = $svc->refundPartial($this->record, $admin, $piasters, $data['reason']);
                        }
                    } catch (\DomainException $e) {
                        \Filament\Notifications\Notification::make()
                            ->title(__('system.refund_not_allowed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                        return;
                    }

                    if ($refund->status === 'failed') {
                        \Filament\Notifications\Notification::make()
                            ->title(__('system.gateway_refund_failed'))
                            ->body(__('system.gateway_refund_failed_body'))
                            ->warning()
                            ->persistent()
                            ->send();
                    } else {
                        \Filament\Notifications\Notification::make()
                            ->title(__('system.refund_issued'))
                            ->success()
                            ->send();
                    }

                    $this->record->refresh();
                    $this->record->load(['refunds.admin']);
                }),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make(__('system.order_information'))
                    ->schema([
                        Forms\Components\TextInput::make('id')
                            ->label(__('system.order_id'))
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\TextInput::make('pickup_code')
                            ->label(__('system.pickup_code'))
                            ->disabled()
                            ->dehydrated()
                            ->copyable(),
                        Forms\Components\Select::make('status')
                            ->label(__('system.status'))
                            ->options([
                                'received' => __('system.received'),
                                'mixing' => __('system.mixing'),
                                'ready' => __('system.ready'),
                                'completed' => __('system.completed'),
                                'cancelled' => __('system.cancelled'),
                            ])
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\DateTimePicker::make('created_at')
                            ->label(__('system.order_date'))
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\DateTimePicker::make('updated_at')
                            ->label(__('system.last_updated'))
                            ->disabled()
                            ->dehydrated(),
                    ])
                    ->columns(3),

                Components\Section::make(__('system.customer_information'))
                    ->schema([
                        Forms\Components\TextInput::make('customer.name')
                            ->label(__('system.customer_name'))
                            ->disabled()
                            ->dehydrated()
                            ->default('N/A'),
                        Forms\Components\TextInput::make('customer_phone')
                            ->label(__('system.phone'))
                            ->disabled()
                            ->dehydrated()
                            ->formatStateUsing(fn () => $this->record->customer
                                ? ($this->record->customer->country_code ?? '') . ($this->record->customer->phone ?? 'N/A')
                                : 'N/A')
                            ->default('N/A'),
                        Forms\Components\TextInput::make('customer.email')
                            ->label(__('system.email'))
                            ->disabled()
                            ->dehydrated()
                            ->default('N/A'),
                    ])
                    ->columns(3)
                    ->visible(fn () => $this->record->customer),

                Components\Section::make(__('system.store_information'))
                    ->schema([
                        Forms\Components\TextInput::make('store.name')
                            ->label(__('system.store_name'))
                            ->disabled()
                            ->dehydrated()
                            ->formatStateUsing(fn () => $this->record->store ? $this->record->store->name : 'N/A')
                            ->default('N/A'),
                        Forms\Components\Textarea::make('store.address')
                            ->label(__('system.address'))
                            ->disabled()
                            ->dehydrated()
                            ->formatStateUsing(fn () => $this->record->store && $this->record->store->address ? $this->record->store->address : 'N/A')
                            ->default('N/A')
                            ->rows(2),
                    ])
                    ->columns(2)
                    ->visible(fn () => $this->record->store),

                Components\Section::make(__('system.payment_information'))
                    ->schema([
                        Forms\Components\TextInput::make('payment_method')
                            ->label(__('system.payment_method'))
                            ->disabled()
                            ->dehydrated()
                            ->formatStateUsing(fn ($state) => ucfirst($state ?? 'N/A')),
                        Forms\Components\TextInput::make('paymentMethod.name')
                            ->label(__('system.payment_method_name'))
                            ->disabled()
                            ->dehydrated()
                            ->default('N/A')
                            ->visible(fn () => $this->record->paymentMethod),
                        Forms\Components\TextInput::make('paymentMethod.code')
                            ->label(__('system.payment_method_code'))
                            ->disabled()
                            ->dehydrated()
                            ->default('N/A')
                            ->visible(fn () => $this->record->paymentMethod),
                    ])
                    ->columns(3),

                Components\Section::make(__('system.order_items'))
                    ->schema([
                        Forms\Components\Placeholder::make('items_display')
                            ->label('')
                            ->content(function () {
                                $items = $this->record->items_snapshot ?? [];
                                if (empty($items) || !is_array($items)) {
                                    return __('system.no_items');
                                }

                                $locale = app()->getLocale();
                                $html = '<div style="font-family: system-ui, -apple-system, sans-serif;">';

                                foreach ($items as $index => $item) {
                                    $productName = $item['name'] ?? ($item['product_name'] ?? __('system.product') . ' #' . ($index + 1));
                                    $productId = $item['product_id'] ?? null;
                                    $productImage = null;

                                    // Try to get product image if product_id exists
                                    if ($productId) {
                                        $product = Product::find($productId);
                                        if ($product && $product->image) {
                                            $productImage = $product->image;
                                        }
                                    }

                                    $quantity = $item['quantity'] ?? 1;
                                    $unitPrice = $item['price'] ?? 0;
                                    $itemTotal = $unitPrice * $quantity;

                                    $html .= '<div style="border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 16px; background: linear-gradient(to right, #ffffff, #f9fafb); box-shadow: 0 2px 4px rgba(0,0,0,0.05);">';
                                    $html .= '<div style="display: flex; gap: 16px; align-items: start;">';

                                    // Product Image
                                    if ($productImage) {
                                        $html .= '<div style="flex-shrink: 0;">';
                                        $html .= '<img src="' . htmlspecialchars($productImage) . '" alt="' . htmlspecialchars($productName) . '" style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px; border: 2px solid #e5e7eb;">';
                                        $html .= '</div>';
                                    }

                                    // Product Details
                                    $html .= '<div style="flex: 1; min-width: 0;">';
                                    $html .= '<h3 style="margin: 0 0 8px 0; font-size: 18px; font-weight: 600; color: #111827;">' . htmlspecialchars($productName) . '</h3>';

                                    // Modifiers
                                    if (isset($item['modifiers']) && is_array($item['modifiers']) && count($item['modifiers']) > 0) {
                                        $html .= '<div style="margin-top: 12px; padding-left: 12px; border-left: 3px solid #3b82f6;">';
                                        $html .= '<p style="margin: 0 0 6px 0; font-size: 13px; font-weight: 500; color: #6b7280;">' . __('system.modifiers') . ':</p>';
                                        $html .= '<ul style="margin: 0; padding-left: 20px; list-style-type: disc;">';
                                        foreach ($item['modifiers'] as $modifier) {
                                            if (is_array($modifier) && isset($modifier['name'])) {
                                                $modifierPrice = isset($modifier['price']) && $modifier['price'] > 0
                                                    ? ' <span style="color: #059669;">(+' . number_format($modifier['price'], 2) . ' EGP)</span>'
                                                    : '';
                                                $html .= '<li style="margin: 4px 0; font-size: 14px; color: #374151;">' . htmlspecialchars($modifier['name']) . $modifierPrice . '</li>';
                                            }
                                        }
                                        $html .= '</ul>';
                                        $html .= '</div>';
                                    }

                                    $html .= '</div>';

                                    // Price Info
                                    $html .= '<div style="flex-shrink: 0; text-align: right; min-width: 150px;">';
                                    $html .= '<div style="margin-bottom: 8px;">';
                                    $html .= '<p style="margin: 0; font-size: 13px; color: #6b7280;">' . __('system.quantity') . '</p>';
                                    $html .= '<p style="margin: 4px 0 0 0; font-size: 16px; font-weight: 600; color: #111827;">' . $quantity . '</p>';
                                    $html .= '</div>';
                                    $html .= '<div style="margin-bottom: 8px;">';
                                    $html .= '<p style="margin: 0; font-size: 13px; color: #6b7280;">' . __('system.unit_price') . '</p>';
                                    $html .= '<p style="margin: 4px 0 0 0; font-size: 16px; font-weight: 600; color: #111827;">' . number_format($unitPrice, 2) . ' EGP</p>';
                                    $html .= '</div>';
                                    $html .= '<div style="padding-top: 8px; border-top: 2px solid #3b82f6; margin-top: 8px;">';
                                    $html .= '<p style="margin: 0; font-size: 13px; color: #6b7280; font-weight: 500;">' . __('system.total') . '</p>';
                                    $html .= '<p style="margin: 4px 0 0 0; font-size: 20px; font-weight: 700; color: #2563eb;">' . number_format($itemTotal, 2) . ' EGP</p>';
                                    $html .= '</div>';
                                    $html .= '</div>';

                                    $html .= '</div>';
                                    $html .= '</div>';
                                }

                                $html .= '</div>';

                                return new \Illuminate\Support\HtmlString($html);
                            })
                            ->columnSpanFull(),
                    ]),

                Components\Section::make(__('system.refund_history'))
                    ->schema([
                        Forms\Components\Placeholder::make('refunds_summary')
                            ->label('')
                            ->content(function () {
                                $totalPiasters    = \App\Support\Money::toPiasters((float) $this->record->total);
                                $refundedPiasters = (int) ($this->record->refunded_amount ?? 0);
                                $statusLabel      = $this->record->refund_status ?? 'none';

                                $statusColor = match ($statusLabel) {
                                    'full', 'voided' => '#dc2626',
                                    'partial'        => '#d97706',
                                    default          => '#6b7280',
                                };

                                $html = '<div style="font-family: system-ui, -apple-system, sans-serif;">';
                                $html .= '<div style="display: flex; gap: 24px; margin-bottom: 16px; padding: 12px 16px; background: #f9fafb; border-radius: 8px;">';
                                $html .= '<div><p style="margin: 0; font-size: 12px; color: #6b7280;">' . __('system.refund_status') . '</p>';
                                $html .= '<p style="margin: 4px 0 0 0; font-size: 14px; font-weight: 600; color: ' . $statusColor . '; text-transform: uppercase;">' . htmlspecialchars($statusLabel) . '</p></div>';
                                $html .= '<div><p style="margin: 0; font-size: 12px; color: #6b7280;">' . __('system.refunded_amount') . '</p>';
                                $html .= '<p style="margin: 4px 0 0 0; font-size: 14px; font-weight: 600; color: #111827;">' . number_format($refundedPiasters / 100, 2) . ' / ' . number_format($totalPiasters / 100, 2) . ' EGP</p></div>';
                                $html .= '</div>';

                                $refunds = $this->record->refunds;
                                if ($refunds->isEmpty()) {
                                    $html .= '<p style="color: #6b7280; font-style: italic;">' . __('system.no_refunds') . '</p>';
                                    $html .= '</div>';
                                    return new \Illuminate\Support\HtmlString($html);
                                }

                                $html .= '<table style="width: 100%; border-collapse: collapse;">';
                                $html .= '<thead><tr style="background: #f3f4f6; text-align: left;">';
                                $html .= '<th style="padding: 8px 12px; font-size: 12px; color: #374151;">' . __('system.type') . '</th>';
                                $html .= '<th style="padding: 8px 12px; font-size: 12px; color: #374151;">' . __('system.amount') . '</th>';
                                $html .= '<th style="padding: 8px 12px; font-size: 12px; color: #374151;">' . __('system.status') . '</th>';
                                $html .= '<th style="padding: 8px 12px; font-size: 12px; color: #374151;">' . __('system.reason') . '</th>';
                                $html .= '<th style="padding: 8px 12px; font-size: 12px; color: #374151;">' . __('system.admin') . '</th>';
                                $html .= '<th style="padding: 8px 12px; font-size: 12px; color: #374151;">' . __('system.date') . '</th>';
                                $html .= '</tr></thead><tbody>';

                                foreach ($refunds as $refund) {
                                    $statusStyle = match ($refund->status) {
                                        'completed' => 'background: #d1fae5; color: #065f46;',
                                        'failed'    => 'background: #fee2e2; color: #991b1b;',
                                        default     => 'background: #fef3c7; color: #92400e;',
                                    };
                                    $html .= '<tr style="border-bottom: 1px solid #e5e7eb;">';
                                    $html .= '<td style="padding: 10px 12px; font-size: 13px; text-transform: capitalize;">' . htmlspecialchars($refund->type) . '</td>';
                                    $html .= '<td style="padding: 10px 12px; font-size: 13px; font-weight: 600;">' . number_format($refund->amount / 100, 2) . ' EGP</td>';
                                    $html .= '<td style="padding: 10px 12px;"><span style="font-size: 11px; padding: 2px 8px; border-radius: 9999px; ' . $statusStyle . '">' . htmlspecialchars($refund->status) . '</span></td>';
                                    $html .= '<td style="padding: 10px 12px; font-size: 13px; max-width: 240px;">' . htmlspecialchars($refund->reason) . '</td>';
                                    $html .= '<td style="padding: 10px 12px; font-size: 13px;">' . htmlspecialchars($refund->admin?->name ?? '—') . '</td>';
                                    $html .= '<td style="padding: 10px 12px; font-size: 13px; color: #6b7280;">' . $refund->created_at->format('M j, Y H:i') . '</td>';
                                    $html .= '</tr>';
                                }

                                $html .= '</tbody></table></div>';

                                return new \Illuminate\Support\HtmlString($html);
                            })
                            ->columnSpanFull(),
                    ])
                    ->visible(fn () => $this->record->refunds->isNotEmpty() || $this->record->refund_status !== 'none'),

                Components\Section::make(__('system.order_totals'))
                    ->schema([
                        Forms\Components\TextInput::make('subtotal')
                            ->label(__('system.subtotal'))
                            ->prefix('EGP')
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\TextInput::make('discount')
                            ->label(__('system.discount'))
                            ->prefix('EGP')
                            ->disabled()
                            ->dehydrated()
                            ->visible(fn () => $this->record->discount > 0)
                            ->formatStateUsing(fn ($state) => '- ' . number_format($state, 2)),
                        Forms\Components\TextInput::make('promoCode.code')
                            ->label(__('system.promo_code'))
                            ->disabled()
                            ->dehydrated()
                            ->default('N/A')
                            ->visible(fn () => $this->record->promoCode),
                        Forms\Components\TextInput::make('promo_discount')
                            ->label(__('system.promo_discount'))
                            ->prefix('EGP')
                            ->disabled()
                            ->dehydrated()
                            ->visible(fn () => $this->record->promo_discount > 0)
                            ->formatStateUsing(fn ($state) => '- ' . number_format($state, 2)),
                        Forms\Components\TextInput::make('tax')
                            ->label(__('system.tax'))
                            ->prefix('EGP')
                            ->disabled()
                            ->dehydrated()
                            ->visible(fn () => $this->record->tax > 0),
                        Forms\Components\TextInput::make('total')
                            ->label(__('system.total'))
                            ->prefix('EGP')
                            ->disabled()
                            ->dehydrated()
                            ->extraAttributes(['class' => 'text-lg font-bold']),
                    ])
                    ->columns(3),
            ]);
    }
}

