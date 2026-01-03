<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Core\Models\Modifier;
use App\Core\Models\ProductModifierGroup;
use Filament\Actions;
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
                    ->getStateUsing(fn ($record) => $record->getName(app()->getLocale()))
                    ->searchable(query: fn ($query, string $search) => 
                        $query->where('name_json->en', 'like', "%{$search}%")
                              ->orWhere('name_json->ar', 'like', "%{$search}%")
                    )
                    ->sortable(query: fn ($query, string $direction) => 
                        $query->orderBy('name_json->' . app()->getLocale(), $direction)
                    ),
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
                Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn (Builder $query) => $query->active())
                    ->form(fn (Actions\AttachAction $action): array => [
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
                Actions\EditAction::make()
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
                Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}

