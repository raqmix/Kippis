<?php

namespace App\Filament\Resources;

use App\Core\Models\Order;
use App\Core\Models\PartnerCode;
use App\Filament\Resources\PartnerCodeResource\Pages;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class PartnerCodeResource extends Resource
{
    protected static ?string $model = PartnerCode::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-user-group';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Marketing';
    }

    public static function getNavigationLabel(): string
    {
        return 'Partner codes';
    }

    public static function canViewAny(): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_promo_codes');
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit($record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete($record): bool
    {
        return false; // deactivate instead: customers stay tagged
    }

    /** Customers tagged with the code who signed up (tagged) in the period. */
    public static function signupCount(PartnerCode $code, ?string $from = null, ?string $until = null): int
    {
        return $code->customers()
            ->when($from, fn ($q) => $q->whereDate('partner_code_set_at', '>=', $from))
            ->when($until, fn ($q) => $q->whereDate('partner_code_set_at', '<=', $until))
            ->count();
    }

    /** Completed orders (any platform, app included) of tagged customers in the period. */
    public static function orderCount(PartnerCode $code, ?string $from = null, ?string $until = null): int
    {
        return Order::query()
            ->whereIn('customer_id', $code->customers()->withTrashed()->select('id'))
            ->where('status', 'completed')
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($until, fn ($q) => $q->whereDate('created_at', '<=', $until))
            ->count();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('code')
                ->required()->maxLength(40)
                ->unique(ignoreRecord: true)
                ->helperText('Case-insensitive; stored in uppercase.')
                ->dehydrateStateUsing(fn ($state) => PartnerCode::normalize($state)),
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\Toggle::make('is_active')->default(true),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        $period = fn ($livewire, string $key) => $livewire->tableFilters['period'][$key] ?? null;

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable()->copyable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('signups')
                    ->label('Sign-ups')
                    ->state(fn (PartnerCode $r, $livewire) => static::signupCount($r, $period($livewire, 'from'), $period($livewire, 'until'))),
                Tables\Columns\TextColumn::make('orders')
                    ->label('Orders (completed)')
                    ->state(fn (PartnerCode $r, $livewire) => static::orderCount($r, $period($livewire, 'from'), $period($livewire, 'until'))),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('period')
                    ->label('Period (counts only)')
                    ->schema([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(fn ($query) => $query), // counts read the filter state above
            ])
            ->recordActions([Actions\EditAction::make()])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePartnerCodes::route('/'),
        ];
    }
}
