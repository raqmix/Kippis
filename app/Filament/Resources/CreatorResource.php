<?php

namespace App\Filament\Resources;

use App\Core\Models\Creator;
use App\Filament\Resources\CreatorResource\Pages;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class CreatorResource extends Resource
{
    protected static ?string $model = Creator::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-user-group';
    }

    public static function getNavigationLabel(): string
    {
        return 'Creators';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Creator Drops';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Components\Section::make('Identity')->schema([
                Forms\Components\TextInput::make('name_en')->required()->maxLength(100)->label('Name (EN)'),
                Forms\Components\TextInput::make('name_ar')->required()->maxLength(100)->label('Name (AR)'),
                Forms\Components\TextInput::make('social_handle')->maxLength(100),
                Forms\Components\Toggle::make('is_active')->default(true),
                Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
            ])->columns(2),

            Components\Section::make('Bio & Avatar')->schema([
                Forms\Components\Textarea::make('bio_en')->label('Bio (EN)'),
                Forms\Components\Textarea::make('bio_ar')->label('Bio (AR)'),
                Forms\Components\FileUpload::make('avatar')
                    ->image()
                    ->directory('creators')
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar')->circular(),
                Tables\Columns\TextColumn::make('name_en')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('social_handle'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->sortable(),
                Tables\Columns\TextColumn::make('sort_order')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
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
            'index'  => Pages\ListCreators::route('/'),
            'create' => Pages\CreateCreator::route('/create'),
            'edit'   => Pages\EditCreator::route('/{record}/edit'),
        ];
    }
}
