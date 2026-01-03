<?php

namespace App\Filament\Resources;

use App\Core\Models\PromoQrCode;
use App\Filament\Resources\PromoQrCodeResource\Pages;
use App\Services\QrCodeGeneratorService;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PromoQrCodeResource extends Resource
{
    protected static ?string $model = PromoQrCode::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-qr-code';

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.customer_management');
    }

    protected static ?int $navigationSort = 8;

    public static function getModelLabel(): string
    {
        return __('system.promotional_qr_code');
    }

    public static function getPluralModelLabel(): string
    {
        return __('system.promotional_qr_codes');
    }

    public static function getNavigationLabel(): string
    {
        return __('navigation.promo_qr_codes');
    }

    public static function canViewAny(): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_promo_qr_codes');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make(__('system.qr_code_information'))
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label(__('system.code'))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->default(fn () => 'PROMO-' . strtoupper(Str::random(8)))
                            ->helperText(__('system.unique_code_string')),
                        Forms\Components\TextInput::make('name')
                            ->label(__('system.name'))
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('points')
                            ->label(__('system.points'))
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->default(10)
                            ->helperText(__('system.points_awarded_helper')),
                    ])
                    ->columns(2),
                Components\Section::make(__('system.status_availability'))
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('system.active'))
                            ->default(true)
                            ->helperText(__('system.enable_disable_qr_code')),
                        Forms\Components\DateTimePicker::make('available_from')
                            ->label(__('system.available_from'))
                            ->required()
                            ->default(now())
                            ->helperText(__('system.when_scanning_becomes_available')),
                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label(__('system.expires_at'))
                            ->nullable()
                            ->helperText(__('system.when_qr_expires')),
                    ])
                    ->columns(3),
                Components\Section::make(__('system.usage_limits'))
                    ->schema([
                        Forms\Components\TextInput::make('max_uses_per_customer')
                            ->label(__('system.max_uses_per_customer'))
                            ->numeric()
                            ->minValue(1)
                            ->nullable()
                            ->helperText(__('system.max_times_customer_use')),
                        Forms\Components\TextInput::make('max_total_uses')
                            ->label(__('system.max_total_uses'))
                            ->numeric()
                            ->minValue(1)
                            ->nullable()
                            ->helperText(__('system.max_total_scans')),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('system.code'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('system.name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('points')
                    ->label(__('system.points'))
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('system.active'))
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_uses_count')
                    ->label(__('system.total_uses'))
                    ->sortable()
                    ->formatStateUsing(fn ($state, $record) => 
                        $record->max_total_uses 
                            ? "{$state} / {$record->max_total_uses}" 
                            : (string) $state
                    ),
                Tables\Columns\TextColumn::make('available_from')
                    ->label(__('system.available_from'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('system.expires_at'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder(__('system.never')),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('system.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_active')
                    ->label(__('system.status'))
                    ->options([
                        true => __('system.active'),
                        false => __('system.inactive'),
                    ]),
                Tables\Filters\Filter::make('available_from')
                    ->form([
                        Forms\Components\DatePicker::make('available_from')
                            ->label(__('system.available_from')),
                    ])
                    ->query(function ($query, array $data) {
                        return $query->when(
                            $data['available_from'],
                            fn ($q, $date) => $q->whereDate('available_from', '>=', $date)
                        );
                    }),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
                Actions\Action::make('generate_qr')
                    ->label(__('system.generate_qr_code'))
                    ->icon('heroicon-o-qr-code')
                    ->color('success')
                    ->action(function (PromoQrCode $record, QrCodeGeneratorService $qrService) {
                        $path = $qrService->generate($record->code);
                        $url = Storage::disk('public')->url($path);
                        
                        \Filament\Notifications\Notification::make()
                            ->title(__('system.qr_code_generated'))
                            ->body(__('system.qr_code_generated_successfully'))
                            ->success()
                            ->send();
                        
                        return redirect($url);
                    }),
                Actions\Action::make('download_qr')
                    ->label(__('system.download_qr_code'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->action(function (PromoQrCode $record, QrCodeGeneratorService $qrService) {
                        return $qrService->download($record->code);
                    }),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPromoQrCodes::route('/'),
            'create' => Pages\CreatePromoQrCode::route('/create'),
            'edit' => Pages\EditPromoQrCode::route('/{record}/edit'),
        ];
    }
}

