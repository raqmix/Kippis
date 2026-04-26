<?php

namespace App\Filament\Resources;

use App\Core\Models\ContentSlot;
use App\Filament\Resources\ContentSlotResource\Pages;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ContentSlotResource extends Resource
{
    protected static ?string $model = ContentSlot::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-photo';
    }

    public static function getNavigationLabel(): string
    {
        return 'Content Slots';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Content';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Components\Section::make('Identity')->schema([
                Forms\Components\TextInput::make('slot_key')
                    ->required()
                    ->maxLength(50)
                    ->helperText('Unique key, e.g. home_hero, app_popup, kiosk_idle'),
                Forms\Components\Toggle::make('is_active')->default(true),
                Forms\Components\CheckboxList::make('platform')
                    ->options(['web' => 'Web', 'mobile' => 'Mobile', 'kiosk' => 'Kiosk'])
                    ->default(['web', 'mobile', 'kiosk'])
                    ->columns(3),
                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
            ])->columns(2),

            Components\Section::make('Content')->schema([
                Forms\Components\TextInput::make('title_en')->required()->maxLength(200)->label('Title (EN)'),
                Forms\Components\TextInput::make('title_ar')->required()->maxLength(200)->label('Title (AR)'),
                Forms\Components\Textarea::make('subtitle_en')->label('Subtitle (EN)'),
                Forms\Components\Textarea::make('subtitle_ar')->label('Subtitle (AR)'),
                Forms\Components\FileUpload::make('image')
                    ->image()
                    ->directory('content-slots')
                    ->columnSpanFull(),
            ])->columns(2),

            Components\Section::make('Call to Action')->schema([
                Forms\Components\TextInput::make('cta_text_en')->maxLength(100)->label('CTA Text (EN)'),
                Forms\Components\TextInput::make('cta_text_ar')->maxLength(100)->label('CTA Text (AR)'),
                Forms\Components\KeyValue::make('cta_action')->label('CTA Action (JSON)')->columnSpanFull(),
            ])->columns(2),

            Components\Section::make('Schedule')->schema([
                Forms\Components\DateTimePicker::make('starts_at')->label('Starts At'),
                Forms\Components\DateTimePicker::make('ends_at')->label('Ends At'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')->square()->size(48),
                Tables\Columns\TextColumn::make('slot_key')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('title_en')->label('Title')->searchable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
                Tables\Columns\TextColumn::make('starts_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('ends_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('sort_order')->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
            ])
            ->defaultSort('slot_key')
            ->reorderable('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListContentSlots::route('/'),
            'create' => Pages\CreateContentSlot::route('/create'),
            'edit'   => Pages\EditContentSlot::route('/{record}/edit'),
        ];
    }
}
