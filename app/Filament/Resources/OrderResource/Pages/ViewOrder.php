<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;

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

    protected function form(Schema $schema): Schema
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
                            ->default('N/A'),
                        Forms\Components\Textarea::make('store.address')
                            ->label(__('system.address'))
                            ->disabled()
                            ->dehydrated()
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
                        Forms\Components\View::make('filament.components.order-items-display')
                            ->viewData([
                                'items' => $this->record->items_snapshot ?? [],
                            ])
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

