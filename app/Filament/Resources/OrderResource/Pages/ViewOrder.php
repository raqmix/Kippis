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
        $this->record->load(['store', 'customer', 'promoCode', 'paymentMethod']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download_pdf')
                ->label(__('system.download_receipt'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->action(function () {
                    $order = $this->record->load(['store', 'customer', 'promoCode', 'paymentMethod']);
                    
                    $pdf = Pdf::loadView('orders.receipt', [
                        'order' => $order,
                        'store' => $order->store,
                        'customer' => $order->customer,
                        'htmlDir' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr',
                    ]);

                    $pdf->setPaper('a4', 'portrait');
                    $pdf->setOption('enable-local-file-access', true);

                    $filename = 'order-' . $order->id . '-' . $order->pickup_code . '.pdf';

                    return $pdf->download($filename);
                }),
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
                                            $productImage = Storage::disk('public')->url($product->image);
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

