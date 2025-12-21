<?php

namespace App\Filament\Resources;

use App\Core\Models\Channel;
use App\Filament\Resources\ChannelResource\Pages;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ChannelResource extends Resource
{
    protected static ?string $model = Channel::class;
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-link';
    }
    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.integrations');
    }
    protected static ?int $navigationSort = 1;
    
    public static function getNavigationLabel(): string
    {
        return __('navigation.channels');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make(__('system.channel_information'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\Select::make('type')
                            ->options([
                                'pos' => __('system.pos'),
                                'payment' => __('system.payment'),
                                'webhook' => __('system.webhook'),
                                'api' => __('system.api'),
                            ])
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => __('system.active'),
                                'inactive' => __('system.inactive'),
                            ])
                            ->required(),
                        Forms\Components\KeyValue::make('credentials')
                            ->label(__('system.credentials'))
                            ->helperText(__('system.credentials_encrypted')),
                        Forms\Components\KeyValue::make('settings')
                            ->label(__('system.settings')),
                        Forms\Components\TextInput::make('webhook_url')
                            ->url(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('last_sync_at')
                    ->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'pos' => __('system.pos'),
                        'payment' => __('system.payment'),
                        'webhook' => __('system.webhook'),
                        'api' => __('system.api'),
                    ]),
            ])
            ->actions([
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
            'index' => Pages\ListChannels::route('/'),
            'create' => Pages\CreateChannel::route('/create'),
            'view' => Pages\ViewChannel::route('/{record}'),
            'edit' => Pages\EditChannel::route('/{record}/edit'),
        ];
    }
}

