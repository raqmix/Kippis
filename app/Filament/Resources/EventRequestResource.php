<?php

namespace App\Filament\Resources;

use App\Core\Models\EventRequest;
use App\Filament\Resources\EventRequestResource\Pages;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class EventRequestResource extends Resource
{
    protected static ?string $model = EventRequest::class;

    protected static ?string $navigationLabel = 'Event Requests';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-calendar-days';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Events';
    }

    public static function getModelLabel(): string
    {
        return 'Event Request';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Event Requests';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make('Contact Details')
                    ->schema([
                        Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('full_name')->label('Full Name')->disabled(),
                            Forms\Components\TextInput::make('email')->label('Email')->disabled(),
                            Forms\Components\TextInput::make('phone_country_code')->label('Country Code')->disabled(),
                            Forms\Components\TextInput::make('phone_number')->label('Phone Number')->disabled(),
                        ]),
                    ])->collapsible(),
                Components\Section::make('Event Details')
                    ->schema([
                        Forms\Components\TextInput::make('event_title')->label('Event Title')->disabled(),
                        Forms\Components\TextInput::make('event_type')->label('Event Type')->disabled(),
                        Components\Grid::make(3)->schema([
                            Forms\Components\DatePicker::make('event_date')->label('Event Date')->disabled(),
                            Forms\Components\TimePicker::make('start_time')->label('Start Time')->disabled(),
                            Forms\Components\TimePicker::make('end_time')->label('End Time')->disabled(),
                        ]),
                    ])->collapsible(),
                Components\Section::make('Location & Guests')
                    ->schema([
                        Forms\Components\TextInput::make('number_of_guests')->label('Number of Guests')->disabled(),
                        Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('city')->label('City')->disabled(),
                            Forms\Components\TextInput::make('region')->label('Region')->disabled(),
                        ]),
                        Forms\Components\Textarea::make('address')->label('Address')->disabled()->rows(2),
                    ])->collapsible(),
                Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'contacted' => 'Contacted',
                                'confirmed' => 'Confirmed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('full_name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('event_title')->searchable()->limit(30),
                Tables\Columns\TextColumn::make('event_type')->badge(),
                Tables\Columns\TextColumn::make('event_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('number_of_guests')->sortable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'contacted' => 'Contacted',
                        'confirmed' => 'Confirmed',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('event_type')
                    ->options([
                        'corporate' => 'Corporate',
                        'wedding' => 'Wedding',
                        'birthday' => 'Birthday',
                        'private_party' => 'Private Party',
                        'reception' => 'Reception',
                        'other' => 'Other',
                    ]),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEventRequests::route('/'),
            'view' => Pages\ViewEventRequest::route('/{record}'),
            'edit' => Pages\EditEventRequest::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
