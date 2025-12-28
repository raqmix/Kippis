<?php

namespace App\Filament\Resources;

use App\Core\Models\Customer;
use App\Filament\Resources\CustomerResource\Pages;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-users';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.customer_management');
    }

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('navigation.customers');
    }

    public static function getModelLabel(): string
    {
        return __('system.customer');
    }

    public static function getPluralModelLabel(): string
    {
        return __('system.customers');
    }

    public static function canViewAny(): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_customers');
    }

    public static function canCreate(): bool
    {
        return false; // Customers are read-only, created via API
    }

    public static function canEdit($record): bool
    {
        return false; // Customers are read-only
    }

    public static function canDelete($record): bool
    {
        return Gate::forUser(auth()->guard('admin')->user())->allows('manage_customers');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make(__('system.customer_information'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('system.name'))
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->label(__('system.email'))
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->label(__('system.phone'))
                            ->required()
                            ->maxLength(20),
                        Forms\Components\TextInput::make('country_code')
                            ->label(__('system.country_code'))
                            ->required()
                            ->maxLength(5),
                        Forms\Components\DatePicker::make('birthdate')
                            ->label(__('system.birthdate'))
                            ->required()
                            ->before('today'),
                        Forms\Components\TextInput::make('password')
                            ->label(__('system.password'))
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $context): bool => $context === 'create')
                            ->minLength(8),
                        Forms\Components\FileUpload::make('avatar')
                            ->label(__('system.avatar'))
                            ->image()
                            ->directory('customers')
                            ->disk('public')
                            ->maxSize(2048)
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                null,
                                '16:9',
                                '4:3',
                                '1:1',
                            ]),
                    ])->columns(2),
                Components\Section::make(__('system.additional_information'))
                    ->schema([
                        Forms\Components\TextInput::make('foodics_customer_id')
                            ->label(__('system.foodics_customer_id'))
                            ->maxLength(255),
                        Forms\Components\Toggle::make('is_verified')
                            ->label(__('system.is_verified'))
                            ->default(false),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar')
                    ->label(__('system.avatar'))
                    ->circular()
                    ->defaultImageUrl(url('/images/default-avatar.png')),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('system.name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label(__('system.email'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label(__('system.phone'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('country_code')
                    ->label(__('system.country_code'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('birthdate')
                    ->label(__('system.birthdate'))
                    ->date()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_verified')
                    ->label(__('system.is_verified'))
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('foodics_customer_id')
                    ->label(__('system.foodics_customer_id'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('system.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->label(__('system.deleted_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_verified')
                    ->label(__('system.is_verified'))
                    ->placeholder(__('system.all'))
                    ->trueLabel(__('system.verified'))
                    ->falseLabel(__('system.unverified')),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Actions\ViewAction::make(),
                // Read-only: Edit and Delete removed
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
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            // Read-only: Create and Edit removed
            'view' => Pages\ViewCustomer::route('/{record}'),
        ];
    }
}
