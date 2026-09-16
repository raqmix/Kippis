<?php

namespace App\Filament\Resources\StoreResource\Pages;

use App\Core\Models\Product;
use App\Core\Services\FoodicsSyncService;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\StoreResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Branch-oriented menu management. From here, an admin picks a store
 * and works only inside that branch's menu — no jumping back to a
 * global list, no filter dance. Three tabs slice the branch by state:
 *
 *  - Live      : on the customer menu right now
 *  - Drafts    : Foodics-imported products waiting on a human review
 *  - Hidden    : globally deactivated (is_active=0) products
 *
 * "Visible at this branch" follows the same rule as the customer API:
 * any product with a pivot row pointing here, OR any product with no
 * pivot rows at all (the legacy "available everywhere" default).
 *
 * NOTE: is_active is currently product-global. Hiding from this page
 * hides everywhere. A future per-pivot is_hidden column would scope
 * the toggle to just this branch.
 */
class BranchMenu extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = StoreResource::class;

    protected string $view = 'filament.resources.store-resource.pages.branch-menu';

    public ?string $activeTab = 'live';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return $this->record->getName(app()->getLocale())
            . ' — ' . __('system.branch_menu');
    }

    public function getBreadcrumb(): string
    {
        return __('system.branch_menu');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sync_from_foodics')
                ->label(__('system.sync_from_foodics'))
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->visible(fn () => filled($this->record->foodics_menu_group_id))
                ->requiresConfirmation()
                ->modalDescription('Pull the latest products from this branch\'s Foodics menu group. New items land as drafts; existing items keep their local sort_order.')
                ->action(function (): void {
                    try {
                        $totals = app(FoodicsSyncService::class)
                            ->syncProductsForStore($this->record);
                        Notification::make()
                            ->title(__('system.sync_completed'))
                            ->body(sprintf(
                                'Created %d · Updated %d · Inactive removed %d',
                                $totals['created'] ?? 0,
                                $totals['updated'] ?? 0,
                                $totals['removed_inactive'] ?? 0,
                            ))
                            ->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title(__('system.sync_failed'))
                            ->body($e->getMessage())
                            ->danger()->send();
                    }
                }),
            Actions\Action::make('back_to_stores')
                ->label(__('system.back_to_stores'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(StoreResource::getUrl('index')),
        ];
    }

    /**
     * Per-tab counts feed the tab labels.
     */
    public function getTabCounts(): array
    {
        return [
            'live' => $this->baseQuery()
                ->where('is_active', true)
                ->where('is_draft', false)
                ->count(),
            'drafts' => $this->baseQuery()
                ->where('is_draft', true)
                ->count(),
            'hidden' => $this->baseQuery()
                ->where('is_active', false)
                ->where('is_draft', false)
                ->count(),
        ];
    }

    /**
     * Products visible at this branch — explicit pivot link OR no pivot
     * rows at all (legacy "available everywhere").
     */
    protected function baseQuery(): Builder
    {
        return Product::query()
            ->availableAtStore($this->record->id);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $q = $this->baseQuery()->with('category');
                return match ($this->activeTab) {
                    'drafts' => $q->where('is_draft', true),
                    'hidden' => $q->where('is_active', false)->where('is_draft', false),
                    default => $q->where('is_active', true)->where('is_draft', false),
                };
            })
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('')
                    ->square()
                    ->size(56)
                    ->disk('public')
                    ->defaultImageUrl(fn ($record) => str_starts_with((string) $record->image, 'http') ? $record->image : null)
                    ->getStateUsing(fn ($record) => str_starts_with((string) $record->image, 'http') ? null : $record->image),
                Tables\Columns\TextColumn::make('name_json')
                    ->label(__('system.name'))
                    ->getStateUsing(fn ($record) => $record->getName(app()->getLocale()))
                    ->weight('semibold')
                    ->searchable(query: fn ($query, string $search) => $query
                        ->where('name_json->en', 'like', "%{$search}%")
                        ->orWhere('name_json->ar', 'like', "%{$search}%"))
                    ->wrap(),
                Tables\Columns\TextColumn::make('category.name_json')
                    ->label(__('system.category'))
                    ->getStateUsing(fn ($record) => $record->category?->getName(app()->getLocale()))
                    ->color('gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('base_price')
                    ->label(__('system.base_price'))
                    ->money('EGP')
                    ->sortable(),
                Tables\Columns\TextColumn::make('source_chip')
                    ->label(__('system.external_source'))
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->external_source ?? 'local')
                    ->color(fn (string $state) => $state === 'foodics' ? 'info' : 'success'),
                Tables\Columns\TextColumn::make('availability_chip')
                    ->label(__('system.availability'))
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->stores()->exists()
                        ? __('system.branch_specific')
                        : __('system.available_everywhere'))
                    ->color(fn (string $state) => $state === __('system.branch_specific') ? 'warning' : 'gray')
                    ->tooltip(__('system.availability_chip_tooltip')),
            ])
            ->groups([
                Group::make('category_id')
                    ->label(__('system.category'))
                    ->getTitleFromRecordUsing(
                        fn ($record) => $record->category?->getName(app()->getLocale())
                            ?? __('system.uncategorized'),
                    )
                    ->collapsible(),
            ])
            ->defaultGroup('category_id')
            ->actions($this->rowActions())
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->defaultSort('sort_order', 'asc');
    }

    /**
     * Tab-aware row actions. Drafts get "Activate"; hidden gets "Show";
     * live gets "Hide". Edit/View live on every tab.
     */
    protected function rowActions(): array
    {
        return [
            Actions\Action::make('activate')
                ->label(__('system.activate'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (Product $record) => $record->is_draft)
                ->requiresConfirmation()
                ->modalDescription('Activate this draft — it will show on the customer menu immediately.')
                ->action(function (Product $record) {
                    $record->update(['is_draft' => false, 'is_active' => true]);
                    Notification::make()->title(__('system.activated'))->success()->send();
                }),
            Actions\Action::make('hide')
                ->label(__('system.hide'))
                ->icon('heroicon-o-eye-slash')
                ->color('warning')
                ->visible(fn (Product $record) => !$record->is_draft && $record->is_active)
                ->requiresConfirmation()
                ->modalHeading(__('system.hide_product'))
                ->modalDescription('Hiding flips is_active off — note this currently hides the product at every branch, not just this one.')
                ->action(function (Product $record) {
                    $record->update(['is_active' => false]);
                    Notification::make()->title(__('system.hidden'))->success()->send();
                }),
            Actions\Action::make('show')
                ->label(__('system.show'))
                ->icon('heroicon-o-eye')
                ->color('success')
                ->visible(fn (Product $record) => !$record->is_draft && !$record->is_active)
                ->action(function (Product $record) {
                    $record->update(['is_active' => true]);
                    Notification::make()->title(__('system.shown'))->success()->send();
                }),
            Actions\Action::make('edit_product')
                ->label(__('system.edit'))
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->url(fn (Product $record) => ProductResource::getUrl('edit', ['record' => $record]))
                ->openUrlInNewTab(),
        ];
    }

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return false;
    }

    public static function canAccess(array $parameters = []): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_stores');
    }
}
