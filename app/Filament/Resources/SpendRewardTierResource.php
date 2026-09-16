<?php

namespace App\Filament\Resources;

use App\Core\Models\Category;
use App\Core\Models\SpendRewardTier;
use App\Filament\Resources\SpendRewardTierResource\Pages;
use App\Support\Money;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class SpendRewardTierResource extends Resource
{
    protected static ?string $model = SpendRewardTier::class;

    protected static ?string $navigationLabel = 'Spend Rewards';

    protected static ?string $modelLabel = 'Spend Reward Tier';

    public static function canViewAny(): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_loyalty');
    }

    public static function canCreate(): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_loyalty');
    }

    public static function canEdit($record): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_loyalty');
    }

    public static function canDelete($record): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_loyalty');
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-trophy';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Marketing';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make('Copy')
                    ->description('Shown to the customer on the Rewards screen. Bilingual.')
                    ->schema([
                        Components\Tabs::make('copy_tabs')
                            ->tabs([
                                Components\Tabs\Tab::make('English')->schema([
                                    Forms\Components\TextInput::make('name_en')
                                        ->label('Name (EN)')
                                        ->required()
                                        ->maxLength(80),
                                    Forms\Components\Textarea::make('description_en')
                                        ->label('Description (EN)')
                                        ->rows(3)
                                        ->maxLength(240),
                                ]),
                                Components\Tabs\Tab::make('Arabic')->schema([
                                    Forms\Components\TextInput::make('name_ar')
                                        ->label('Name (AR)')
                                        ->required()
                                        ->maxLength(80),
                                    Forms\Components\Textarea::make('description_ar')
                                        ->label('Description (AR)')
                                        ->rows(3)
                                        ->maxLength(240),
                                ]),
                            ]),
                    ]),

                Components\Section::make('Threshold & cycle')
                    ->schema([
                        Forms\Components\TextInput::make('threshold_egp')
                            ->label('Threshold (EGP)')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->helperText('Customer earns this reward when cumulative spend crosses this amount.')
                            ->formatStateUsing(fn ($state, $record) => $record
                                ? Money::piastersToDecimalString((int) $record->threshold_piasters)
                                : null)
                            ->dehydrated(false),
                        Forms\Components\Select::make('cycle')
                            ->options([
                                'once' => 'Once per customer (lifetime)',
                                'repeating' => 'Repeating (every threshold crossing)',
                            ])
                            ->default('once')
                            ->required(),
                        Forms\Components\TextInput::make('voucher_ttl_days')
                            ->label('Voucher expiry (days)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(3650)
                            ->helperText('Leave blank for no expiry. Otherwise the voucher expires this many days after being issued.'),
                    ])
                    ->columns(3),

                Components\Section::make('Reward choice groups')
                    ->description(
                        'Each group is one "pick one" slot the customer fills at redemption. For "salad OR sandwich + drink", make TWO groups: one with the salad + sandwich categories, one with the drinks category.'
                    )
                    ->schema([
                        Forms\Components\Repeater::make('choice_groups')
                            ->label('Choice groups')
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('label_en')
                                        ->label('Group name (EN)')
                                        ->required()
                                        ->maxLength(40)
                                        ->placeholder('Main'),
                                    Forms\Components\TextInput::make('label_ar')
                                        ->label('Group name (AR)')
                                        ->required()
                                        ->maxLength(40)
                                        ->placeholder('الوجبة الرئيسية'),
                                ]),
                                Forms\Components\Select::make('category_ids')
                                    ->label('Eligible categories')
                                    ->multiple()
                                    ->required()
                                    ->options(fn () => Category::query()
                                        ->orderBy('id')
                                        ->get()
                                        ->mapWithKeys(fn ($c) => [
                                            $c->id => method_exists($c, 'getName')
                                                ? $c->getName(app()->getLocale())
                                                : ($c->name_json['en'] ?? "Category #{$c->id}"),
                                        ])
                                        ->all())
                                    ->helperText('Customer picks one product from any of these categories.'),
                                Forms\Components\TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->helperText('Almost always 1 — how many products the customer gets from this group.'),
                            ])
                            ->itemLabel(fn (array $state): ?string => $state['label_en'] ?? null)
                            ->reorderable()
                            ->collapsible()
                            ->defaultItems(1)
                            ->minItems(1),
                    ]),

                Components\Section::make('Activation window')
                    ->schema([
                        Forms\Components\Toggle::make('active')
                            ->label('Active')
                            ->default(true),
                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label('Starts at')
                            ->helperText('Leave blank for no start restriction.'),
                        Forms\Components\DateTimePicker::make('ends_at')
                            ->label('Ends at')
                            ->helperText('Leave blank for no end.'),
                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Higher = shown first on the Rewards screen.'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        // Convert the EGP input to piasters for storage.
        if (isset($data['threshold_egp'])) {
            $data['threshold_piasters'] = Money::toPiasters((float) $data['threshold_egp']);
            unset($data['threshold_egp']);
        }
        return $data;
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        return self::mutateFormDataBeforeCreate($data);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('name_en')
                    ->label('Name')
                    ->searchable()
                    ->description(fn (SpendRewardTier $r) => $r->name_ar),
                Tables\Columns\TextColumn::make('threshold_piasters')
                    ->label('Threshold')
                    ->formatStateUsing(fn ($state) => 'EGP ' . Money::piastersToDecimalString((int) $state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('cycle')
                    ->badge()
                    ->colors([
                        'gray' => 'once',
                        'success' => 'repeating',
                    ]),
                Tables\Columns\TextColumn::make('choice_groups')
                    ->label('Groups')
                    ->formatStateUsing(fn ($state) => is_array($state) ? count($state) . ' group(s)' : '—'),
                Tables\Columns\IconColumn::make('active')->boolean()->sortable(),
                Tables\Columns\TextColumn::make('starts_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('sort_order', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('active'),
                Tables\Filters\SelectFilter::make('cycle')->options([
                    'once' => 'Once',
                    'repeating' => 'Repeating',
                ]),
            ])
            ->actions([
                Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSpendRewardTiers::route('/'),
            'create' => Pages\CreateSpendRewardTier::route('/create'),
            'edit' => Pages\EditSpendRewardTier::route('/{record}/edit'),
        ];
    }
}
