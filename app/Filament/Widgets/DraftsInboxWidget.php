<?php

namespace App\Filament\Widgets;

use App\Core\Models\Product;
use App\Filament\Resources\StoreResource;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Drafts inbox: every product imported by Foodics sync that's still
 * `is_draft = 1`, with branch chips so admin can see at a glance where
 * each one came from. One-click "Activate" flips it live; "Review at
 * branch" jumps to that branch's BranchMenu page. The widget hides
 * itself entirely when there are zero drafts so the dashboard stays
 * clean on a settled menu.
 */
class DraftsInboxWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -1;

    protected static ?string $heading = 'Drafts awaiting review';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Product::query()
                ->where('is_draft', true)
                ->with(['category', 'stores'])
                ->orderBy('created_at', 'desc'))
            ->emptyStateHeading(__('system.no_drafts'))
            ->emptyStateDescription(__('system.no_drafts_description'))
            ->emptyStateIcon('heroicon-o-check-badge')
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('')
                    ->square()
                    ->disk('public')
                    ->defaultImageUrl(fn ($record) => str_starts_with((string) $record->image, 'http') ? $record->image : null)
                    ->getStateUsing(fn ($record) => str_starts_with((string) $record->image, 'http') ? null : $record->image),
                Tables\Columns\TextColumn::make('name_json')
                    ->label(__('system.name'))
                    ->getStateUsing(fn ($record) => $record->getName(app()->getLocale()))
                    ->weight('semibold'),
                Tables\Columns\TextColumn::make('category.name_json')
                    ->label(__('system.category'))
                    ->getStateUsing(fn ($record) => $record->category?->getName(app()->getLocale()))
                    ->color('gray'),
                Tables\Columns\TextColumn::make('branch_chips')
                    ->label(__('system.branches'))
                    ->badge()
                    ->getStateUsing(function ($record) {
                        $names = $record->stores->map(fn ($s) => $s->getName(app()->getLocale()))->all();
                        return empty($names) ? [__('system.available_everywhere')] : $names;
                    })
                    ->separator(','),
                Tables\Columns\TextColumn::make('base_price')
                    ->label(__('system.base_price'))
                    ->money('EGP'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('system.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Actions\Action::make('activate')
                    ->label(__('system.activate'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Product $record) {
                        $record->update(['is_draft' => false, 'is_active' => true]);
                    }),
                Actions\Action::make('open_branch_menu')
                    ->label(__('system.review_at_branch'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->visible(fn (Product $record) => $record->stores->isNotEmpty())
                    ->url(fn (Product $record) => StoreResource::getUrl(
                        'branch-menu',
                        ['record' => $record->stores->first()->id]
                    )),
            ])
            ->bulkActions([
                Actions\BulkAction::make('activate_selected')
                    ->label(__('system.activate_selected'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        DB::transaction(function () use ($records) {
                            foreach ($records as $record) {
                                $record->update(['is_draft' => false, 'is_active' => true]);
                            }
                        });
                    }),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }

    public static function canView(): bool
    {
        return Product::query()->where('is_draft', true)->exists();
    }
}
