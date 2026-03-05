<?php

namespace App\Filament\Resources;

use App\Core\Models\Product;
use App\Core\Models\Promotion;
use App\Filament\Resources\PromotionResource\Pages;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class PromotionResource extends Resource
{
    protected static ?string $model = Promotion::class;

    protected static ?string $navigationLabel = 'Promotions';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-sparkles';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Marketing';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make('Content')
                    ->schema([
                        Forms\Components\TextInput::make('title')->label('Title')->required()->maxLength(100),
                        Forms\Components\Textarea::make('offer_text')->label('Offer Text')->required()->rows(2),
                        Forms\Components\FileUpload::make('image')
                            ->label('Image')
                            ->image()
                            ->directory('promotions')
                            ->disk('public')
                            ->maxSize(2048),
                        Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('cta_text')->label('CTA Text')->default('Get It Now!')->maxLength(100),
                            Forms\Components\TextInput::make('cta_link')->label('CTA Link')->url()->maxLength(500),
                            Forms\Components\TextInput::make('dismiss_text')->label('Dismiss Text')->placeholder('Maybe Later')->maxLength(50),
                        ]),
                        Forms\Components\Select::make('product_id')
                        ->label('Product for this offer')
                        ->relationship('product', 'name_json')
                        ->getOptionLabelFromRecordUsing(fn (Product $r) => $r->getName())
                        ->searchable()
                        ->preload()
                        ->placeholder('None')
                        ->nullable(),
                    ]),
                Components\Section::make('Schedule & Order')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')->label('Active')->default(true),
                        Components\Grid::make(2)->schema([
                            Forms\Components\DateTimePicker::make('starts_at')->label('Starts At'),
                            Forms\Components\DateTimePicker::make('ends_at')->label('Ends At'),
                        ]),
                        Forms\Components\TextInput::make('sort_order')->label('Sort Order')->numeric()->minValue(0)->default(0),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('product.name_json')->label('Product')->formatStateUsing(fn ($state, $r) => $r?->product?->getName()),
                Tables\Columns\ImageColumn::make('image')->disk('public')->circular(),
                Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('offer_text')->limit(40)->wrap(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->sortable(),
                Tables\Columns\TextColumn::make('starts_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('ends_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('sort_order')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
            ])
            ->actions([
                Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPromotions::route('/'),
            'create' => Pages\CreatePromotion::route('/create'),
            'edit' => Pages\EditPromotion::route('/{record}/edit'),
        ];
    }
}
