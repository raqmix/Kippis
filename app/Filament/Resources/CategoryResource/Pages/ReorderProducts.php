<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Core\Models\Category;
use App\Core\Models\Product;
use App\Filament\Resources\CategoryResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Focused product-reorder page scoped to one category. From here an
 * admin sees nothing but the products in this category, a thumbnail,
 * the name, a price, and a drag handle. The main Products table still
 * supports drag reorder within a category-group too, but this page
 * exists for when you just want to sort one category without scanning
 * past the others.
 *
 * Linked from the Categories list via the row action 'Reorder products'.
 */
class ReorderProducts extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = CategoryResource::class;

    protected string $view = 'filament.resources.category-resource.pages.reorder-products';

    public Category $record;

    public function mount(int|string $record): void
    {
        $this->record = Category::findOrFail($record);
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('system.reorder_products') . ' — ' . $this->record->getName(app()->getLocale());
    }

    public function getBreadcrumb(): string
    {
        return __('system.reorder');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back_to_categories')
                ->label(__('system.back_to_categories'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(CategoryResource::getUrl('index')),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Product::query()
                ->where('category_id', $this->record->id)
                ->orderBy('sort_order', 'asc'))
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->weight('bold')
                    ->color('gray'),
                Tables\Columns\ImageColumn::make('image')
                    ->label('')
                    ->square()
                    ->size(56)
                    ->disk('public')
                    ->defaultImageUrl(fn ($record) => str_starts_with((string)$record->image, 'http') ? $record->image : null)
                    ->getStateUsing(fn ($record) => str_starts_with((string)$record->image, 'http') ? null : $record->image),
                Tables\Columns\TextColumn::make('name_json')
                    ->label(__('system.name'))
                    ->getStateUsing(fn ($record) => $record->getName(app()->getLocale()))
                    ->weight('semibold')
                    ->wrap(),
                Tables\Columns\TextColumn::make('base_price')
                    ->label(__('system.base_price'))
                    ->money('EGP')
                    ->color('gray'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('system.active'))
                    ->boolean(),
            ])
            ->paginated(false)
            ->actions([
                Actions\Action::make('move_to_top')
                    ->label(__('system.move_to_top'))
                    ->icon('heroicon-o-bars-arrow-up')
                    ->color('gray')
                    ->action(function (Product $record): void {
                        $minOrder = (int) Product::where('category_id', $this->record->id)->min('sort_order');
                        $record->update(['sort_order' => $minOrder - 1]);
                        $this->resequence();
                        Notification::make()
                            ->title(__('system.moved_to_top'))
                            ->success()->send();
                    }),
                Actions\Action::make('move_to_bottom')
                    ->label(__('system.move_to_bottom'))
                    ->icon('heroicon-o-bars-arrow-down')
                    ->color('gray')
                    ->action(function (Product $record): void {
                        $maxOrder = (int) Product::where('category_id', $this->record->id)->max('sort_order');
                        $record->update(['sort_order' => $maxOrder + 1]);
                        $this->resequence();
                        Notification::make()
                            ->title(__('system.moved_to_bottom'))
                            ->success()->send();
                    }),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order', 'asc');
    }

    /**
     * After a manual move-to-top/bottom, sort_order can drift to
     * negative or far-out values. Re-pack to 1..N within this category
     * so the column stays human-readable in the main table.
     */
    private function resequence(): void
    {
        $products = Product::where('category_id', $this->record->id)
            ->orderBy('sort_order', 'asc')
            ->get();
        foreach ($products as $i => $product) {
            $product->update(['sort_order' => $i + 1]);
        }
    }

    public static function getNavigationLabel(): string
    {
        return __('system.reorder_products');
    }

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        // Only reachable via the row action on Categories — not in the
        // sidebar.
        return false;
    }

    /**
     * Filament returns 404 (not 403) for unauthorized resource pages to
     * hide their existence. Anyone with the manage_categories gate —
     * same gate that authorises the Foodics sync button — can reorder.
     */
    public static function canAccess(array $parameters = []): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_categories');
    }
}
