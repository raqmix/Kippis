<?php

namespace App\Filament\Resources;

use App\Core\Models\Creator;
use App\Core\Models\CreatorDrop;
use App\Core\Models\Product;
use App\Filament\Resources\CreatorDropResource\Pages;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class CreatorDropResource extends Resource
{
    protected static ?string $model = CreatorDrop::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-fire';
    }

    public static function getNavigationLabel(): string
    {
        return 'Creator Drops';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Creator Drops';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Components\Section::make('Details')->schema([
                Forms\Components\Select::make('creator_id')
                    ->relationship('creator', 'name_en')
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('product_id')
                    ->relationship('product', 'name_en')
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options([
                        'draft'     => 'Draft',
                        'scheduled' => 'Scheduled',
                        'live'      => 'Live',
                        'ended'     => 'Ended',
                        'cancelled' => 'Cancelled',
                    ])
                    ->default('draft')
                    ->required(),
                Forms\Components\TextInput::make('max_quantity')->numeric()->nullable(),
                Forms\Components\TextInput::make('notify_before_minutes')->numeric()->default(60)->label('Notify Before (minutes)'),
            ])->columns(2),

            Components\Section::make('Content')->schema([
                Forms\Components\TextInput::make('title_en')->required()->maxLength(200)->label('Title (EN)'),
                Forms\Components\TextInput::make('title_ar')->required()->maxLength(200)->label('Title (AR)'),
                Forms\Components\Textarea::make('description_en')->label('Description (EN)'),
                Forms\Components\Textarea::make('description_ar')->label('Description (AR)'),
                Forms\Components\FileUpload::make('cover_image')
                    ->image()
                    ->directory('creator-drops')
                    ->columnSpanFull(),
            ])->columns(2),

            Components\Section::make('Schedule')->schema([
                Forms\Components\DateTimePicker::make('starts_at')->required(),
                Forms\Components\DateTimePicker::make('ends_at')->required(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('creator.name_en')->label('Creator')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('title_en')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'live'      => 'success',
                        'scheduled' => 'info',
                        'ended'     => 'gray',
                        'cancelled' => 'danger',
                        default     => 'warning',
                    }),
                Tables\Columns\TextColumn::make('starts_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('ends_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('quantity_sold')->sortable(),
            ])
            ->defaultSort('starts_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft'     => 'Draft',
                        'scheduled' => 'Scheduled',
                        'live'      => 'Live',
                        'ended'     => 'Ended',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->actions([
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
            'index'  => Pages\ListCreatorDrops::route('/'),
            'create' => Pages\CreateCreatorDrop::route('/create'),
            'edit'   => Pages\EditCreatorDrop::route('/{record}/edit'),
        ];
    }
}
