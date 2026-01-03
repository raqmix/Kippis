<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Core\Models\MixBuilderBase;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MixBuilderBasesRelationManager extends RelationManager
{
    protected static string $relationship = 'mixBuilderBases';

    protected static ?string $title = 'Mix Builder Assignments';

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $modelLabel = 'Mix Builder Base';

    protected static ?string $pluralModelLabel = 'Mix Builder Bases';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('mix_builder_id')
                    ->label('Builder ID')
                    ->formatStateUsing(fn ($state) => $state ?? 'Global')
                    ->badge()
                    ->color(fn ($state) => $state ? 'info' : 'success')
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->formatStateUsing(fn ($record) => $record->product->getName(app()->getLocale()))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.base_price')
                    ->label('Base Price')
                    ->money('SAR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('mix_builder_id')
                    ->form([
                        Forms\Components\TextInput::make('builder_id')
                            ->label('Builder ID')
                            ->numeric(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['builder_id'],
                                fn (Builder $query, $builderId): Builder => $query->where('mix_builder_id', $builderId),
                            );
                    }),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->form([
                        Forms\Components\TextInput::make('mix_builder_id')
                            ->label('Mix Builder ID')
                            ->numeric()
                            ->helperText('Leave empty for global bases (available to all builders)')
                            ->nullable(),
                    ])
                    ->mutateFormDataUsing(function (array $data, $livewire): array {
                        $data['product_id'] = $livewire->getOwnerRecord()->id;
                        return $data;
                    }),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->form([
                        Forms\Components\TextInput::make('mix_builder_id')
                            ->label('Mix Builder ID')
                            ->numeric()
                            ->helperText('Leave empty for global bases (available to all builders)')
                            ->nullable(),
                    ]),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No mix builder assignments')
            ->emptyStateDescription('Assign this product as a base to specific mix builders or leave builder ID empty for global availability.')
            ->emptyStateIcon('heroicon-o-cube');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        // Only show this relation manager for mix_base products
        return $ownerRecord->product_kind === 'mix_base';
    }
}

