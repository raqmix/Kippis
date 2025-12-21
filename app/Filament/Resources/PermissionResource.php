<?php

namespace App\Filament\Resources;

use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Permission\Models\Permission;

class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-key';
    }
    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.system_management');
    }
    protected static ?int $navigationSort = 3;
    
    public static function getNavigationLabel(): string
    {
        return __('navigation.permissions');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make(__('system.permission_information'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
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
            ])
            ->filters([
                //
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
            'index' => \App\Filament\Resources\PermissionResource\Pages\ListPermissions::route('/'),
            'create' => \App\Filament\Resources\PermissionResource\Pages\CreatePermission::route('/create'),
            'view' => \App\Filament\Resources\PermissionResource\Pages\ViewPermission::route('/{record}'),
            'edit' => \App\Filament\Resources\PermissionResource\Pages\EditPermission::route('/{record}/edit'),
        ];
    }
}

