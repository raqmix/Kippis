<?php

namespace App\Filament\Resources;

use App\Core\Models\QrCode;
use App\Filament\Resources\QrCodeResource\Pages;
use App\Filament\Resources\QrCodeResource\RelationManagers;
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

class QrCodeResource extends Resource
{
    protected static ?string $model = QrCode::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-qr-code';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.customer_management');
    }

    protected static ?int $navigationSort = 6;

    public static function getNavigationLabel(): string
    {
        return __('navigation.qr_codes');
    }

    public static function getModelLabel(): string
    {
        return __('system.qr_code');
    }

    public static function getPluralModelLabel(): string
    {
        return __('system.qr_codes');
    }

    public static function canViewAny(): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_qr_codes');
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
                            ->default(fn () => 'QR-' . strtoupper(Str::random(8)))
                            ->helperText(__('system.unique_code_string')),
                        Forms\Components\TextInput::make('title')
                            ->label(__('system.title'))
                            ->maxLength(255)
                            ->nullable(),
                        Forms\Components\Textarea::make('description')
                            ->label(__('system.description'))
                            ->rows(3)
                            ->nullable(),
                        Forms\Components\TextInput::make('points_awarded')
                            ->label(__('system.points_awarded'))
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
                        Forms\Components\DateTimePicker::make('start_at')
                            ->label(__('system.start_at'))
                            ->nullable()
                            ->helperText(__('system.when_qr_becomes_valid')),
                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label(__('system.expires_at'))
                            ->nullable()
                            ->helperText(__('system.when_qr_expires'))
                            ->after('start_at'),
                    ])
                    ->columns(3),
                Components\Section::make(__('system.usage_limits'))
                    ->schema([
                        Forms\Components\TextInput::make('per_customer_limit')
                            ->label(__('system.per_customer_limit'))
                            ->numeric()
                            ->minValue(1)
                            ->nullable()
                            ->helperText(__('system.max_times_one_customer')),
                        Forms\Components\TextInput::make('total_limit')
                            ->label(__('system.total_limit'))
                            ->numeric()
                            ->minValue(1)
                            ->nullable()
                            ->helperText(__('system.max_total_redemptions')),
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
                Tables\Columns\TextColumn::make('title')
                    ->label(__('system.title'))
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('points_awarded')
                    ->label(__('system.points'))
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('system.active'))
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_used_count')
                    ->label(__('system.total_uses'))
                    ->sortable()
                    ->formatStateUsing(fn ($state, $record) => 
                        $record->total_limit 
                            ? "{$state} / {$record->total_limit}" 
                            : (string) $state
                    ),
                Tables\Columns\TextColumn::make('per_customer_limit')
                    ->label(__('system.per_customer'))
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state ? (string) $state : __('system.unlimited'))
                    ->placeholder(__('system.unlimited')),
                Tables\Columns\TextColumn::make('start_at')
                    ->label(__('system.start_at'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder(__('system.immediate')),
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
                Tables\Filters\Filter::make('expired')
                    ->label(__('system.expired'))
                    ->query(fn ($query) => $query->expired()),
                Tables\Filters\Filter::make('upcoming')
                    ->label(__('system.upcoming'))
                    ->query(fn ($query) => $query->where('start_at', '>', now())),
                Tables\Filters\Filter::make('has_remaining')
                    ->label(__('system.has_remaining_uses'))
                    ->query(function ($query) {
                        return $query->where(function ($q) {
                            $q->whereNull('total_limit')
                              ->orWhereColumn('total_used_count', '<', 'total_limit');
                        });
                    }),
                Tables\Filters\Filter::make('fully_used')
                    ->label(__('system.fully_used'))
                    ->query(function ($query) {
                        return $query->whereNotNull('total_limit')
                            ->whereColumn('total_used_count', '>=', 'total_limit');
                    }),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
                Actions\Action::make('generate_qr')
                    ->label(__('system.generate_qr_code'))
                    ->icon('heroicon-o-qr-code')
                    ->color('success')
                    ->action(function (QrCode $record, QrCodeGeneratorService $qrService) {
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
                    ->action(function (QrCode $record, QrCodeGeneratorService $qrService) {
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

    public static function getRelations(): array
    {
        return [
            RelationManagers\UsagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQrCodes::route('/'),
            'create' => Pages\CreateQrCode::route('/create'),
            'edit' => Pages\EditQrCode::route('/{record}/edit'),
            'view' => Pages\ViewQrCode::route('/{record}'),
        ];
    }
}

