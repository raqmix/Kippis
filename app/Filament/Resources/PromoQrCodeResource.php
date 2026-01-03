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

    protected static ?string $navigationIcon = 'heroicon-o-qr-code';

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.customer_management');
    }

    protected static ?int $navigationSort = 8;

    public static function getModelLabel(): string
    {
        return 'Promotional QR Code';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Promotional QR Codes';
    }

    public static function canViewAny(): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_promo_qr_codes');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make('QR Code Information')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->default(fn () => 'PROMO-' . strtoupper(Str::random(8)))
                            ->helperText('Unique code string that will be embedded in the QR code'),
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Admin-friendly name/description for this QR code'),
                        Forms\Components\TextInput::make('points')
                            ->label('Points')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->default(10)
                            ->helperText('Points awarded when this QR code is scanned'),
                    ])
                    ->columns(2),
                Components\Section::make('Status & Availability')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Enable or disable this QR code'),
                        Forms\Components\DateTimePicker::make('available_from')
                            ->label('Available From')
                            ->required()
                            ->default(now())
                            ->helperText('When scanning becomes available'),
                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Expires At')
                            ->nullable()
                            ->helperText('When QR code expires (leave empty for no expiration)'),
                    ])
                    ->columns(3),
                Components\Section::make('Usage Limits')
                    ->schema([
                        Forms\Components\TextInput::make('max_uses_per_customer')
                            ->label('Max Uses Per Customer')
                            ->numeric()
                            ->minValue(1)
                            ->nullable()
                            ->helperText('Maximum times one customer can use this (leave empty for unlimited)'),
                        Forms\Components\TextInput::make('max_total_uses')
                            ->label('Max Total Uses')
                            ->numeric()
                            ->minValue(1)
                            ->nullable()
                            ->helperText('Maximum total scans across all customers (leave empty for unlimited)'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('points')
                    ->label('Points')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_uses_count')
                    ->label('Total Uses')
                    ->sortable()
                    ->formatStateUsing(fn ($state, $record) => 
                        $record->max_total_uses 
                            ? "{$state} / {$record->max_total_uses}" 
                            : (string) $state
                    ),
                Tables\Columns\TextColumn::make('available_from')
                    ->label('Available From')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires At')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Never'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        true => 'Active',
                        false => 'Inactive',
                    ]),
                Tables\Filters\Filter::make('available_from')
                    ->form([
                        Forms\Components\DatePicker::make('available_from')
                            ->label('Available From'),
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
                    ->label('Generate QR Code')
                    ->icon('heroicon-o-qr-code')
                    ->color('success')
                    ->action(function (PromoQrCode $record, QrCodeGeneratorService $qrService) {
                        $path = $qrService->generate($record->code);
                        $url = Storage::disk('public')->url($path);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('QR Code Generated')
                            ->body('QR code has been generated successfully.')
                            ->success()
                            ->send();
                        
                        return redirect($url);
                    }),
                Actions\Action::make('download_qr')
                    ->label('Download QR Code')
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

