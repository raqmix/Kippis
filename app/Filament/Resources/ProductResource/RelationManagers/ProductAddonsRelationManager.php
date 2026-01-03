<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Core\Models\Modifier;
use App\Core\Models\ProductModifierGroup;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductAddonsRelationManager extends RelationManager
{
    protected static string $relationship = 'addonModifiers';

    protected static ?string $title = 'Product Addons';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        // Only show this relation manager for regular products (not mix_base)
        return $ownerRecord->product_kind === 'regular';
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Modifier')
                    ->formatStateUsing(fn ($record) => $record->getName(app()->getLocale()))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sweetness' => 'warning',
                        'fizz' => 'info',
                        'caffeine' => 'danger',
                        'extra' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\IconColumn::make('pivot.is_required')
                    ->label('Required')
                    ->boolean(),
                Tables\Columns\TextColumn::make('pivot.min_select')
                    ->label('Min Level')
                    ->formatStateUsing(fn ($state) => $state ?? '0'),
                Tables\Columns\TextColumn::make('pivot.max_select')
                    ->label('Max Level')
                    ->formatStateUsing(fn ($state) => $state ?? 'N/A'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->form([
                        Forms\Components\Select::make('modifier_id')
                            ->label('Modifier')
                            ->relationship('modifier', 'name_json')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->getName(app()->getLocale()))
                            ->searchable()
                            ->required()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\Select::make('type')
                                    ->label('Type')
                                    ->options([
                                        'sweetness' => 'Sweetness',
                                        'fizz' => 'Fizz',
                                        'caffeine' => 'Caffeine',
                                        'extra' => 'Extra',
                                    ])
                                    ->required(),
                                Forms\Components\TextInput::make('name_json.en')
                                    ->label('Name (English)')
                                    ->required(),
                                Forms\Components\TextInput::make('name_json.ar')
                                    ->label('Name (Arabic)')
                                    ->required(),
                                Forms\Components\TextInput::make('price')
                                    ->label('Price')
                                    ->numeric()
                                    ->default(0),
                            ]),
                        Forms\Components\Toggle::make('is_required')
                            ->label('Required')
                            ->default(false),
                        Forms\Components\TextInput::make('min_select')
                            ->label('Min Select (Level)')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Minimum level to select (0 = optional)'),
                        Forms\Components\TextInput::make('max_select')
                            ->label('Max Select (Level)')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Maximum level to select'),
                    ])
                    ->using(function (array $data, $livewire): \Illuminate\Database\Eloquent\Model {
                        $product = $livewire->getOwnerRecord();
                        return ProductModifierGroup::create([
                            'product_id' => $product->id,
                            'modifier_id' => $data['modifier_id'],
                            'is_required' => $data['is_required'] ?? false,
                            'min_select' => $data['min_select'] ?? null,
                            'max_select' => $data['max_select'] ?? null,
                        ]);
                    }),
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn (Builder $query) => $query->active())
                    ->form(fn (Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Forms\Components\Toggle::make('is_required')
                            ->label('Required')
                            ->default(false),
                        Forms\Components\TextInput::make('min_select')
                            ->label('Min Select (Level)')
                            ->numeric()
                            ->minValue(0),
                        Forms\Components\TextInput::make('max_select')
                            ->label('Max Select (Level)')
                            ->numeric()
                            ->minValue(0),
                    ])
                    ->using(function (array $data, $livewire, $record): Model {
                        $product = $livewire->getOwnerRecord();
                        return ProductModifierGroup::updateOrCreate(
                            [
                                'product_id' => $product->id,
                                'modifier_id' => $record->id,
                            ],
                            [
                                'is_required' => $data['is_required'] ?? false,
                                'min_select' => $data['min_select'] ?? null,
                                'max_select' => $data['max_select'] ?? null,
                            ]
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->form([
                        Forms\Components\Toggle::make('is_required')
                            ->label('Required'),
                        Forms\Components\TextInput::make('min_select')
                            ->label('Min Select (Level)')
                            ->numeric()
                            ->minValue(0),
                        Forms\Components\TextInput::make('max_select')
                            ->label('Max Select (Level)')
                            ->numeric()
                            ->minValue(0),
                    ])
                    ->using(function (array $data, $record, $livewire) {
                        $product = $livewire->getOwnerRecord();
                        ProductModifierGroup::where('product_id', $product->id)
                            ->where('modifier_id', $record->id)
                            ->update([
                                'is_required' => $data['is_required'] ?? false,
                                'min_select' => $data['min_select'] ?? null,
                                'max_select' => $data['max_select'] ?? null,
                            ]);
                    }),
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}

