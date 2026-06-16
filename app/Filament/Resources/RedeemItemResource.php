<?php

namespace App\Filament\Resources;

use App\Core\Models\Product;
use App\Core\Models\RedeemItem;
use App\Core\Models\Store;
use App\Filament\Resources\RedeemItemResource\Pages;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class RedeemItemResource extends Resource
{
    protected static ?string $model = RedeemItem::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-gift';
    }

    protected static ?int $navigationSort = 51;

    public static function getNavigationLabel(): string
    {
        return __('system.redeem_items');
    }

    public static function getModelLabel(): string
    {
        return __('system.redeem_item');
    }

    public static function getPluralModelLabel(): string
    {
        return __('system.redeem_items');
    }

    public static function canAccess(): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_settings');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Components\Section::make(__('system.redeem_item_details'))
                ->icon('heroicon-o-gift')
                ->schema([
                    Components\Tabs::make('title_tabs')
                        ->label(__('system.title'))
                        ->tabs([
                            Components\Tabs\Tab::make('en')->label('English')->schema([
                                Forms\Components\TextInput::make('title_json.en')
                                    ->label(__('system.title'))
                                    ->required()->maxLength(120),
                            ]),
                            Components\Tabs\Tab::make('ar')->label('Arabic')->schema([
                                Forms\Components\TextInput::make('title_json.ar')
                                    ->label(__('system.title'))
                                    ->required()->maxLength(120),
                            ]),
                        ])
                        ->columnSpanFull(),

                    Components\Tabs::make('description_tabs')
                        ->label(__('system.description'))
                        ->tabs([
                            Components\Tabs\Tab::make('en')->label('English')->schema([
                                Forms\Components\Textarea::make('description_json.en')
                                    ->label(__('system.description'))->rows(3),
                            ]),
                            Components\Tabs\Tab::make('ar')->label('Arabic')->schema([
                                Forms\Components\Textarea::make('description_json.ar')
                                    ->label(__('system.description'))->rows(3),
                            ]),
                        ])
                        ->columnSpanFull(),

                    Forms\Components\FileUpload::make('image')
                        ->label(__('system.image'))
                        ->image()
                        ->directory('redeem-items')
                        ->disk('public')
                        ->maxSize(2048)
                        ->imageEditor(),

                    Forms\Components\Select::make('product_id')
                        ->label(__('system.linked_product'))
                        ->helperText(__('system.linked_product_help'))
                        ->options(fn () => Product::query()
                            ->active()
                            ->get()
                            ->mapWithKeys(fn ($p) => [
                                $p->id => $p->getName(app()->getLocale()),
                            ])
                            ->all())
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    Forms\Components\TextInput::make('points_cost')
                        ->label(__('system.points_cost'))
                        ->numeric()->minValue(1)->required(),

                    Forms\Components\Toggle::make('is_active')
                        ->label(__('system.is_active'))
                        ->default(true),

                    Forms\Components\TextInput::make('sort_order')
                        ->label(__('system.sort_order'))
                        ->numeric()->minValue(0)->default(0),
                ])
                ->columns(2),

            Components\Section::make(__('system.branch_availability'))
                ->icon('heroicon-o-map-pin')
                ->description(__('system.redeem_item_branch_help'))
                ->schema([
                    Forms\Components\Select::make('stores')
                        ->label(__('system.available_at_branches'))
                        ->multiple()
                        ->relationship('stores', 'name')
                        ->preload()
                        ->searchable()
                        ->helperText(__('system.empty_means_all_branches')),
                ]),

            Components\Section::make(__('system.limits'))
                ->icon('heroicon-o-shield-check')
                ->description(__('system.redeem_limits_help'))
                ->schema([
                    Forms\Components\TextInput::make('max_per_customer_lifetime')
                        ->label(__('system.max_per_customer_lifetime'))
                        ->helperText(__('system.empty_means_no_cap'))
                        ->numeric()->minValue(1)->nullable(),
                    Forms\Components\TextInput::make('max_per_customer_per_day')
                        ->label(__('system.max_per_customer_per_day'))
                        ->helperText(__('system.empty_means_no_cap'))
                        ->numeric()->minValue(1)->nullable(),
                    Forms\Components\TextInput::make('max_global')
                        ->label(__('system.max_global'))
                        ->helperText(__('system.empty_means_no_cap'))
                        ->numeric()->minValue(1)->nullable(),
                    Forms\Components\TextInput::make('wallet_ttl_days')
                        ->label(__('system.wallet_ttl_days'))
                        ->helperText(__('system.wallet_ttl_help'))
                        ->numeric()->minValue(1)->nullable(),
                ])
                ->columns(2)
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')->label('#')->color('gray'),
                Tables\Columns\ImageColumn::make('image')
                    ->label('')
                    ->square()
                    ->size(48)
                    ->disk('public'),
                Tables\Columns\TextColumn::make('title_json')
                    ->label(__('system.title'))
                    ->getStateUsing(fn ($record) => $record->getTitle(app()->getLocale()))
                    ->weight('semibold')
                    ->searchable(query: fn ($query, string $search) => $query
                        ->where('title_json->en', 'like', "%{$search}%")
                        ->orWhere('title_json->ar', 'like', "%{$search}%")),
                Tables\Columns\TextColumn::make('points_cost')
                    ->label(__('system.points_cost'))
                    ->color('warning'),
                Tables\Columns\TextColumn::make('product.name_json')
                    ->label(__('system.linked_product'))
                    ->getStateUsing(fn ($record) => $record->product?->getName(app()->getLocale()) ?? '—'),
                Tables\Columns\TextColumn::make('stores_count')
                    ->label(__('system.branches'))
                    ->counts('stores')
                    ->getStateUsing(fn ($record) => $record->stores->isEmpty()
                        ? __('system.all_branches_short')
                        : $record->stores->count()),
                Tables\Columns\IconColumn::make('is_active')->label(__('system.active'))->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('system.is_active'))
                    ->placeholder(__('system.all'))
                    ->trueLabel(__('system.active'))
                    ->falseLabel(__('system.inactive')),
                Tables\Filters\SelectFilter::make('store')
                    ->label(__('system.branch'))
                    ->options(fn () => Store::query()
                        ->get()
                        ->mapWithKeys(fn ($s) => [$s->id => $s->getNameLocalized(app()->getLocale())])
                        ->all())
                    ->query(function ($query, array $data) {
                        if (! isset($data['value']) || $data['value'] === null) {
                            return $query;
                        }
                        $storeId = (int) $data['value'];
                        return $query->where(function ($q) use ($storeId) {
                            $q->whereDoesntHave('stores')
                              ->orWhereHas('stores', fn ($s) => $s->where('stores.id', $storeId));
                        });
                    }),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
                Actions\RestoreAction::make(),
                Actions\ForceDeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                    Actions\RestoreBulkAction::make(),
                    Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRedeemItems::route('/'),
            'create' => Pages\CreateRedeemItem::route('/create'),
            'edit'   => Pages\EditRedeemItem::route('/{record}/edit'),
        ];
    }
}
