<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FoodicsTokenResource\Pages;
use App\Integrations\Foodics\Models\FoodicsToken;
use App\Integrations\Foodics\Services\FoodicsAuthService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class FoodicsTokenResource extends Resource
{
    protected static ?string $model = FoodicsToken::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-arrow-path';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.integrations');
    }

    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return __('system.foodics_test');
    }

    public static function getModelLabel(): string
    {
        return 'Foodics Token';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Foodics API Tokens';
    }

    public static function canViewAny(): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_categories');
    }

    public static function canCreate(): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_categories');
    }

    public static function canEdit($record): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_categories');
    }

    public static function canDelete($record): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_categories');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make('Token Configuration')
                    ->schema([
                        Forms\Components\Select::make('mode')
                            ->options([
                                'sandbox' => 'Sandbox',
                                'live' => 'Live',
                            ])
                            ->required()
                            ->default('sandbox'),
                        Forms\Components\TextInput::make('token_type')
                            ->label('Token Type')
                            ->default('Bearer'),
                        Forms\Components\Textarea::make('access_token')
                            ->label('Bearer Token')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('expires_in')
                            ->label('Expires In (seconds)')
                            ->numeric()
                            ->nullable()
                            ->helperText('Optional. Leave blank if the token does not expire.'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('mode')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sandbox' => 'warning',
                        'live'    => 'success',
                        default   => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('token_type')
                    ->label('Type')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('access_token')
                    ->label('Token Preview')
                    ->formatStateUsing(fn (string $state): string => substr($state, 0, 30) . '...')
                    ->tooltip('Truncated — use Edit to view or update the full token'),
                Tables\Columns\IconColumn::make('valid')
                    ->label('Status')
                    ->state(fn (FoodicsToken $record): bool => !$record->isExpired())
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires At')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Never'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Added At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('mode')
            ->actions([
                Actions\Action::make('test')
                    ->label('Test')
                    ->icon('heroicon-o-signal')
                    ->color('info')
                    ->action(function (FoodicsToken $record): void {
                        try {
                            $result = app(FoodicsAuthService::class)->testAuthentication($record->mode);

                            if ($result['success']) {
                                Notification::make()
                                    ->title(ucfirst($record->mode) . ': Connection Successful')
                                    ->body($result['message'])
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title(ucfirst($record->mode) . ': Connection Failed')
                                    ->body($result['message'])
                                    ->danger()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListFoodicsTokens::route('/'),
            'create' => Pages\CreateFoodicsToken::route('/create'),
            'edit'   => Pages\EditFoodicsToken::route('/{record}/edit'),
        ];
    }
}
