<?php

namespace App\Filament\Resources;

use App\Core\Models\PromoCode;
use App\Filament\Resources\PromoCodeResource\Pages;
use App\Filament\Resources\PromoCodeResource\RelationManagers\UsagesRelationManager;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class PromoCodeResource extends Resource
{
    protected static ?string $model = PromoCode::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-ticket';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.content_management');
    }

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return __('navigation.promo_codes');
    }

    public static function getModelLabel(): string
    {
        return __('system.promo_code');
    }

    public static function getPluralModelLabel(): string
    {
        return __('system.promo_codes');
    }

    public static function canViewAny(): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_promo_codes');
    }

    public static function canCreate(): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_promo_codes');
    }

    public static function canEdit($record): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_promo_codes');
    }

    public static function canDelete($record): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_promo_codes');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make('Display')
                    ->description('Customer-facing name and description shown on the cart screen and receipt. Bilingual.')
                    ->schema([
                        Components\Tabs::make('name_json_tabs')
                            ->label('Name')
                            ->tabs([
                                Components\Tabs\Tab::make('en')->schema([
                                    Forms\Components\TextInput::make('name_json.en')
                                        ->label('Name (EN)')
                                        ->maxLength(255),
                                ]),
                                Components\Tabs\Tab::make('ar')->schema([
                                    Forms\Components\TextInput::make('name_json.ar')
                                        ->label('Name (AR)')
                                        ->maxLength(255),
                                ]),
                            ])->columnSpanFull(),
                        Components\Tabs::make('description_json_tabs')
                            ->label('Description')
                            ->tabs([
                                Components\Tabs\Tab::make('en')->schema([
                                    Forms\Components\Textarea::make('description_json.en')
                                        ->label('Description (EN)')
                                        ->rows(2)
                                        ->maxLength(500),
                                ]),
                                Components\Tabs\Tab::make('ar')->schema([
                                    Forms\Components\Textarea::make('description_json.ar')
                                        ->label('Description (AR)')
                                        ->rows(2)
                                        ->maxLength(500),
                                ]),
                            ])->columnSpanFull(),
                    ])
                    ->collapsible(),

                Components\Section::make('Trigger')
                    ->description('How the customer activates this promo. Auto-apply runs on every cart recalculate; a code field is also allowed for code-based campaigns.')
                    ->schema([
                        Forms\Components\Toggle::make('auto_apply')
                            ->label('Auto-apply (no code needed)')
                            ->default(false)
                            ->live(),
                        Forms\Components\TextInput::make('code')
                            ->label('Promo Code')
                            ->required(fn ($get) => !$get('auto_apply'))
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->dehydrateStateUsing(fn ($state) => $state ? strtoupper($state) : null)
                            ->helperText('Leave blank for purely auto-applied promos.'),
                        Forms\Components\TextInput::make('priority')
                            ->label('Priority')
                            ->numeric()
                            ->default(0)
                            ->helperText('Higher priority wins when multiple non-stackable promos match.'),
                        Forms\Components\Toggle::make('stackable')
                            ->label('Stackable')
                            ->default(false)
                            ->helperText('Stackable promos combine with every other stackable + one non-stackable.'),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Components\Section::make('Discount')
                    ->description('What the customer actually gets.')
                    ->schema([
                        Forms\Components\Select::make('discount_type')
                            ->label('Type')
                            ->options([
                                PromoCode::TYPE_PERCENTAGE => 'Percentage off',
                                PromoCode::TYPE_FIXED => 'Fixed amount off',
                                PromoCode::TYPE_BUY_X_GET_Y => 'Buy X, get Y free',
                                PromoCode::TYPE_FREE_ITEM => 'Free item',
                                PromoCode::TYPE_FREE_DELIVERY => 'Free delivery',
                            ])
                            ->required()
                            ->live(),

                        // Percentage / fixed types both use discount_value.
                        Forms\Components\TextInput::make('discount_value')
                            ->label(fn ($get) => $get('discount_type') === PromoCode::TYPE_PERCENTAGE
                                ? 'Percent'
                                : 'Amount')
                            ->numeric()
                            ->step(0.01)
                            ->suffix(fn ($get) => $get('discount_type') === PromoCode::TYPE_PERCENTAGE ? '%' : 'EGP')
                            ->required(fn ($get) => in_array($get('discount_type'), [PromoCode::TYPE_PERCENTAGE, PromoCode::TYPE_FIXED], true))
                            ->visible(fn ($get) => in_array($get('discount_type'), [PromoCode::TYPE_PERCENTAGE, PromoCode::TYPE_FIXED], true)),

                        // buy_x_get_y config
                        Forms\Components\TextInput::make('config.buy')
                            ->label('Buy quantity (X)')
                            ->numeric()
                            ->minValue(1)
                            ->required(fn ($get) => $get('discount_type') === PromoCode::TYPE_BUY_X_GET_Y)
                            ->visible(fn ($get) => $get('discount_type') === PromoCode::TYPE_BUY_X_GET_Y),
                        Forms\Components\TextInput::make('config.get')
                            ->label('Get free (Y)')
                            ->numeric()
                            ->minValue(1)
                            ->required(fn ($get) => $get('discount_type') === PromoCode::TYPE_BUY_X_GET_Y)
                            ->visible(fn ($get) => $get('discount_type') === PromoCode::TYPE_BUY_X_GET_Y)
                            ->helperText('Cheapest Y items of every (X + Y) in the matching category/product set are free.'),

                        // free_item config — options sourced via a plain
                        // closure (NOT ->relationship()) because the
                        // resource already syncs the M2M `products`
                        // pivot in the Scope section below. Reusing the
                        // relation here would double-write on save.
                        Forms\Components\Select::make('config.product_id')
                            ->label('Free product')
                            ->options(fn () => \App\Core\Models\Product::query()
                                ->orderBy('id')
                                ->get(['id', 'name_json'])
                                ->mapWithKeys(fn ($p) => [$p->id => $p->getName(app()->getLocale())])
                                ->all())
                            ->searchable()
                            ->required(fn ($get) => $get('discount_type') === PromoCode::TYPE_FREE_ITEM)
                            ->visible(fn ($get) => $get('discount_type') === PromoCode::TYPE_FREE_ITEM),
                        Forms\Components\TextInput::make('config.quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->visible(fn ($get) => $get('discount_type') === PromoCode::TYPE_FREE_ITEM),

                        Forms\Components\TextInput::make('minimum_order_amount')
                            ->label('Minimum order amount')
                            ->numeric()
                            ->prefix('EGP')
                            ->default(0)
                            ->step(0.01),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Components\Section::make('Conditions')
                    ->description('Who and when. All conditions must pass.')
                    ->schema([
                        Forms\Components\Toggle::make('conditions.first_order_only')
                            ->label('First order only')
                            ->helperText('Only customers who have never placed a completed order.'),
                        Forms\Components\CheckboxList::make('conditions.segments')
                            ->label('Customer segments')
                            ->options([
                                PromoCode::SEGMENT_EVERYONE => 'Everyone',
                                PromoCode::SEGMENT_NEW_CUSTOMER => 'New customer (0 orders)',
                                PromoCode::SEGMENT_LOYAL => 'Loyal (5+ orders)',
                                PromoCode::SEGMENT_STAFF => 'Staff',
                            ])
                            ->columns(2)
                            ->helperText('If none selected → everyone.'),
                        Forms\Components\CheckboxList::make('conditions.days_of_week')
                            ->label('Days of week')
                            ->options([
                                1 => 'Monday',
                                2 => 'Tuesday',
                                3 => 'Wednesday',
                                4 => 'Thursday',
                                5 => 'Friday',
                                6 => 'Saturday',
                                7 => 'Sunday',
                            ])
                            ->columns(4)
                            ->helperText('If none selected → every day.'),
                        Forms\Components\TimePicker::make('conditions.time_window.from')
                            ->label('Valid from (time of day)')
                            ->seconds(false),
                        Forms\Components\TimePicker::make('conditions.time_window.to')
                            ->label('Valid until (time of day)')
                            ->seconds(false),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Components\Section::make('Validity & limits')
                    ->schema([
                        Forms\Components\DateTimePicker::make('valid_from')
                            ->label(__('system.valid_from'))
                            ->required(),
                        Forms\Components\DateTimePicker::make('valid_to')
                            ->label(__('system.valid_to'))
                            ->required()
                            ->after('valid_from'),
                        Forms\Components\TextInput::make('usage_limit')
                            ->label(__('system.usage_limit'))
                            ->numeric()
                            ->minValue(1),
                        Forms\Components\TextInput::make('usage_per_user_limit')
                            ->label(__('system.usage_per_user_limit'))
                            ->numeric()
                            ->minValue(1),
                        Forms\Components\Toggle::make('active')
                            ->label(__('system.active'))
                            ->default(true)
                            ->required(),
                        Forms\Components\Toggle::make('visible_to_customer')
                            ->label('Show in Offers screen')
                            ->helperText('When on, this promo appears in the customer-facing Offers list (Flutter Offers tab + web /offers page). Auto-applied internal promos should stay off.')
                            ->default(false),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Components\Section::make(__('system.scoping'))
                    ->description('Which branches / categories / products this promo can apply to. Leave empty for global.')
                    ->schema([
                        Forms\Components\CheckboxList::make('stores')
                            ->label(__('system.stores'))
                            ->relationship('stores', 'name')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->name)
                            ->columns(2),
                        Forms\Components\CheckboxList::make('categories')
                            ->label(__('system.categories'))
                            ->relationship('categories', 'name_json')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->getName(app()->getLocale()))
                            ->columns(2),
                        Forms\Components\CheckboxList::make('products')
                            ->label(__('system.products'))
                            ->relationship('products', 'name_json')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->getName(app()->getLocale()))
                            ->columns(2),
                    ])
                    ->columns(1)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name_json')
                    ->label('Name')
                    ->getStateUsing(fn ($record) => $record->getName(app()->getLocale()))
                    ->searchable(query: function ($query, $search) {
                        return $query->where('name_json', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->label(__('system.code'))
                    ->copyable()
                    ->placeholder('— auto —')
                    ->sortable(),
                Tables\Columns\IconColumn::make('auto_apply')
                    ->label('Auto')
                    ->boolean(),
                Tables\Columns\TextColumn::make('discount_type')
                    ->label(__('system.discount_type'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        PromoCode::TYPE_PERCENTAGE => 'info',
                        PromoCode::TYPE_FIXED => 'success',
                        PromoCode::TYPE_BUY_X_GET_Y => 'warning',
                        PromoCode::TYPE_FREE_ITEM => 'primary',
                        PromoCode::TYPE_FREE_DELIVERY => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('priority')
                    ->label('Priority')
                    ->sortable(),
                Tables\Columns\TextColumn::make('valid_from')
                    ->label(__('system.valid_from'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('valid_to')
                    ->label(__('system.valid_to'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('usage_limit')
                    ->label('Usage')
                    ->formatStateUsing(fn ($state, $record) => $state ? "{$record->used_count}/{$state}" : (string) $record->used_count)
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('active')
                    ->label(__('system.active'))
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('visible_to_customer')
                    ->label('In Offers')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active')
                    ->label(__('system.active')),
                Tables\Filters\TernaryFilter::make('visible_to_customer')
                    ->label('Visible in Offers'),
                Tables\Filters\TernaryFilter::make('auto_apply')
                    ->label('Auto-apply'),
                Tables\Filters\SelectFilter::make('discount_type')
                    ->label(__('system.discount_type'))
                    ->options([
                        PromoCode::TYPE_PERCENTAGE => 'Percentage',
                        PromoCode::TYPE_FIXED => 'Fixed',
                        PromoCode::TYPE_BUY_X_GET_Y => 'Buy X get Y',
                        PromoCode::TYPE_FREE_ITEM => 'Free item',
                        PromoCode::TYPE_FREE_DELIVERY => 'Free delivery',
                    ]),
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
            ->defaultSort('priority', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            UsagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPromoCodes::route('/'),
            'create' => Pages\CreatePromoCode::route('/create'),
            'view' => Pages\ViewPromoCode::route('/{record}'),
            'edit' => Pages\EditPromoCode::route('/{record}/edit'),
        ];
    }
}
