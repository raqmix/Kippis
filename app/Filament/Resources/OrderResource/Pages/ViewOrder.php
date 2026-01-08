<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

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

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make(__('system.order_information'))
                    ->schema([
                        TextEntry::make('id')
                            ->label(__('system.order_id'))
                            ->badge()
                            ->color('primary'),
                        TextEntry::make('pickup_code')
                            ->label(__('system.pickup_code'))
                            ->badge()
                            ->color('success')
                            ->copyable(),
                        TextEntry::make('status')
                            ->label(__('system.status'))
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'received' => 'info',
                                'mixing' => 'warning',
                                'ready' => 'success',
                                'completed' => 'gray',
                                'cancelled' => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'received' => __('system.received'),
                                'mixing' => __('system.mixing'),
                                'ready' => __('system.ready'),
                                'completed' => __('system.completed'),
                                'cancelled' => __('system.cancelled'),
                                default => $state,
                            }),
                        TextEntry::make('created_at')
                            ->label(__('system.order_date'))
                            ->dateTime()
                            ->icon('heroicon-o-calendar'),
                        TextEntry::make('updated_at')
                            ->label(__('system.last_updated'))
                            ->dateTime()
                            ->icon('heroicon-o-clock'),
                    ])
                    ->columns(3),
                
                Section::make(__('system.customer_information'))
                    ->schema([
                        TextEntry::make('customer.name')
                            ->label(__('system.customer_name'))
                            ->default('N/A'),
                        TextEntry::make('customer.phone')
                            ->label(__('system.phone'))
                            ->formatStateUsing(fn ($record) => $record && $record->customer 
                                ? ($record->customer->country_code ?? '') . ($record->customer->phone ?? 'N/A')
                                : 'N/A')
                            ->default('N/A'),
                        TextEntry::make('customer.email')
                            ->label(__('system.email'))
                            ->default('N/A'),
                    ])
                    ->columns(3)
                    ->visible(fn () => $this->record->customer),
                
                Section::make(__('system.store_information'))
                    ->schema([
                        TextEntry::make('store.name')
                            ->label(__('system.store_name'))
                            ->default('N/A'),
                        TextEntry::make('store.address')
                            ->label(__('system.address'))
                            ->default('N/A'),
                    ])
                    ->columns(2)
                    ->visible(fn () => $this->record->store),
                
                Section::make(__('system.payment_information'))
                    ->schema([
                        TextEntry::make('payment_method')
                            ->label(__('system.payment_method'))
                            ->badge()
                            ->color('info')
                            ->formatStateUsing(fn ($state) => ucfirst($state ?? 'N/A')),
                        TextEntry::make('paymentMethod.name')
                            ->label(__('system.payment_method_name'))
                            ->default('N/A')
                            ->visible(fn () => $this->record->paymentMethod),
                        TextEntry::make('paymentMethod.code')
                            ->label(__('system.payment_method_code'))
                            ->badge()
                            ->color('gray')
                            ->default('N/A')
                            ->visible(fn () => $this->record->paymentMethod),
                    ])
                    ->columns(3),
                
                Section::make(__('system.order_items'))
                    ->schema([
                        TextEntry::make('items_snapshot')
                            ->label('')
                            ->formatStateUsing(function ($state) {
                                if (empty($state) || !is_array($state)) {
                                    return __('system.no_items');
                                }
                                
                                $html = '<div class="space-y-4">';
                                foreach ($state as $index => $item) {
                                    $html .= '<div class="border rounded-lg p-4 bg-gray-50">';
                                    $html .= '<div class="flex justify-between items-start mb-2">';
                                    $html .= '<div class="flex-1">';
                                    $html .= '<h4 class="font-semibold text-lg">' . ($item['product_name'] ?? 'Product #' . ($index + 1)) . '</h4>';
                                    
                                    // Show modifiers if available
                                    if (isset($item['modifiers']) && is_array($item['modifiers']) && count($item['modifiers']) > 0) {
                                        $html .= '<div class="mt-2 ml-4">';
                                        $html .= '<p class="text-sm font-medium text-gray-600 mb-1">' . __('system.modifiers') . ':</p>';
                                        $html .= '<ul class="list-disc list-inside space-y-1">';
                                        foreach ($item['modifiers'] as $modifier) {
                                            if (is_array($modifier) && isset($modifier['name'])) {
                                                $modifierPrice = isset($modifier['price']) && $modifier['price'] > 0 
                                                    ? ' (+' . number_format($modifier['price'], 2) . ' EGP)' 
                                                    : '';
                                                $html .= '<li class="text-sm text-gray-700">' . $modifier['name'] . $modifierPrice . '</li>';
                                            }
                                        }
                                        $html .= '</ul>';
                                        $html .= '</div>';
                                    }
                                    
                                    $html .= '</div>';
                                    $html .= '<div class="text-right">';
                                    $html .= '<p class="text-sm text-gray-600">' . __('system.quantity') . ': <span class="font-semibold">' . ($item['quantity'] ?? 1) . '</span></p>';
                                    $html .= '<p class="text-sm text-gray-600">' . __('system.unit_price') . ': <span class="font-semibold">' . number_format($item['price'] ?? 0, 2) . ' EGP</span></p>';
                                    $itemTotal = ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
                                    $html .= '<p class="text-lg font-bold text-primary-600 mt-1">' . __('system.total') . ': ' . number_format($itemTotal, 2) . ' EGP</p>';
                                    $html .= '</div>';
                                    $html .= '</div>';
                                    $html .= '</div>';
                                }
                                $html .= '</div>';
                                
                                return new \Illuminate\Support\HtmlString($html);
                            })
                            ->columnSpanFull(),
                    ]),
                
                Section::make(__('system.order_totals'))
                    ->schema([
                        TextEntry::make('subtotal')
                            ->label(__('system.subtotal'))
                            ->money('EGP')
                            ->icon('heroicon-o-calculator'),
                        TextEntry::make('discount')
                            ->label(__('system.discount'))
                            ->money('EGP')
                            ->icon('heroicon-o-tag')
                            ->visible(fn () => $this->record->discount > 0)
                            ->formatStateUsing(fn ($state) => '- ' . number_format($state, 2)),
                        TextEntry::make('promoCode.code')
                            ->label(__('system.promo_code'))
                            ->badge()
                            ->color('success')
                            ->default('N/A')
                            ->visible(fn () => $this->record->promoCode),
                        TextEntry::make('promo_discount')
                            ->label(__('system.promo_discount'))
                            ->money('EGP')
                            ->icon('heroicon-o-gift')
                            ->visible(fn () => $this->record->promo_discount > 0)
                            ->formatStateUsing(fn ($state) => '- ' . number_format($state, 2)),
                        TextEntry::make('tax')
                            ->label(__('system.tax'))
                            ->money('EGP')
                            ->icon('heroicon-o-receipt-percent')
                            ->visible(fn () => $this->record->tax > 0),
                        TextEntry::make('total')
                            ->label(__('system.total'))
                            ->money('EGP')
                            ->icon('heroicon-o-currency-dollar')
                            ->size('lg')
                            ->weight('bold')
                            ->color('success'),
                    ])
                    ->columns(3),
            ]);
    }
}

