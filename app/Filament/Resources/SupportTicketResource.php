<?php

namespace App\Filament\Resources;

use App\Core\Models\SupportTicket;
use App\Filament\Resources\SupportTicketResource\Pages;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-ticket';
    }
    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.support');
    }
    protected static ?int $navigationSort = 1;
    
    public static function getNavigationLabel(): string
    {
        return __('navigation.support_tickets');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make(__('system.contact_information'))
                    ->icon('heroicon-o-user-circle')
                    ->description(__('system.contact_information_description'))
                    ->schema([
                        Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('customer_id')
                                    ->label(__('system.customer'))
                                    ->relationship('customer', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->columnSpan(1)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if ($state) {
                                            $customer = \App\Core\Models\Customer::find($state);
                                            if ($customer) {
                                                $set('name', $customer->name);
                                                $set('email', $customer->email);
                                            }
                                        }
                                    }),
                                Forms\Components\TextInput::make('name')
                                    ->label(__('system.name'))
                                    ->placeholder(__('system.enter_name'))
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('email')
                                    ->label(__('system.email'))
                                    ->email()
                                    ->placeholder(__('system.enter_email'))
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(1),
                            ]),
                    ])
                    ->collapsible(),

                Components\Section::make(__('system.ticket_details'))
                    ->icon('heroicon-o-ticket')
                    ->description(__('system.ticket_details_description'))
                    ->schema([
                        Forms\Components\TextInput::make('ticket_number')
                            ->label(__('system.ticket_number'))
                            ->disabled()
                            ->dehydrated()
                            ->visible(fn ($record) => $record !== null),
                        Forms\Components\TextInput::make('subject')
                            ->label(__('system.subject'))
                            ->placeholder(__('system.enter_subject'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('message')
                            ->label(__('system.message'))
                            ->placeholder(__('system.enter_message'))
                            ->required()
                            ->rows(6)
                            ->columnSpanFull()
                            ->extraAttributes([
                                'class' => 'min-h-[150px]',
                            ]),
                    ])
                    ->collapsible(),

                Components\Section::make(__('system.ticket_management'))
                    ->icon('heroicon-o-cog-6-tooth')
                    ->description(__('system.ticket_management_description'))
                    ->schema([
                        Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->label(__('system.status'))
                                    ->options([
                                        'open' => __('system.open'),
                                        'in_progress' => __('system.in_progress'),
                                        'closed' => __('system.closed'),
                                    ])
                                    ->default('open')
                                    ->required()
                                    ->native(false)
                                    ->columnSpan(1),
                                Forms\Components\Select::make('priority')
                                    ->label(__('system.priority'))
                                    ->options([
                                        'low' => __('system.low'),
                                        'medium' => __('system.medium'),
                                        'high' => __('system.high'),
                                    ])
                                    ->default('medium')
                                    ->native(false)
                                    ->columnSpan(1),
                                Forms\Components\Select::make('assigned_to')
                                    ->label(__('system.assigned_to'))
                                    ->relationship('assignedTo', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->columnSpan(1),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ticket_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('system.customer'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('system.name'))
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('priority')
                    ->badge(),
                Tables\Columns\TextColumn::make('assignedTo.name')
                    ->label(__('system.assigned_to')),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'open' => __('system.open'),
                        'in_progress' => __('system.in_progress'),
                        'closed' => __('system.closed'),
                    ]),
                Tables\Filters\SelectFilter::make('priority')
                    ->options([
                        'low' => __('system.low'),
                        'medium' => __('system.medium'),
                        'high' => __('system.high'),
                    ]),
                Tables\Filters\SelectFilter::make('customer_id')
                    ->label(__('system.customer'))
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
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
            'index' => Pages\ListSupportTickets::route('/'),
            'create' => Pages\CreateSupportTicket::route('/create'),
            'view' => Pages\ViewSupportTicket::route('/{record}'),
            'edit' => Pages\EditSupportTicket::route('/{record}/edit'),
        ];
    }
}

