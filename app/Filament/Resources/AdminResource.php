<?php

namespace App\Filament\Resources;

use App\Core\Models\Admin;
use App\Filament\Resources\AdminResource\Pages;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class AdminResource extends Resource
{
    protected static ?string $model = Admin::class;
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-users';
    }
    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.system_management');
    }
    protected static ?int $navigationSort = 1;
    
    public static function getNavigationLabel(): string
    {
        return __('navigation.admins');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make(__('system.basic_information'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\DateTimePicker::make('updated_at')
                            ->label(__('system.updated_at'))
                            ->displayFormat('d/m/Y H:i')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn ($record) => $record !== null),
                        Forms\Components\Select::make('locale')
                            ->label(__('system.language'))
                            ->options([
                                'en' => 'English',
                                'ar' => 'Arabic',
                            ])
                            ->default('en')
                            ->required(),
                    ]),
                    
                Components\Section::make(__('system.security_settings'))
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('system.active'))
                            ->default(true),
                        Forms\Components\Toggle::make('two_factor_enabled')
                            ->label(__('system.enable_2fa'))
                            ->disabled(fn ($record) => $record && !$record->two_factor_secret),
                    ]),
                    
                Components\Section::make(__('system.access_control'))
                    ->schema([
                        Forms\Components\TagsInput::make('allowed_ips')
                            ->label(__('system.allowed_ips')),
                        Forms\Components\TimePicker::make('access_start_time'),
                        Forms\Components\TimePicker::make('access_end_time'),
                        Forms\Components\CheckboxList::make('allowed_days')
                            ->options([
                                'monday' => __('system.monday'),
                                'tuesday' => __('system.tuesday'),
                                'wednesday' => __('system.wednesday'),
                                'thursday' => __('system.thursday'),
                                'friday' => __('system.friday'),
                                'saturday' => __('system.saturday'),
                                'sunday' => __('system.sunday'),
                            ]),
                    ]),
                    
                Components\Section::make(__('system.password'))
                    ->schema([
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->required(fn ($livewire) => $livewire instanceof Pages\CreateAdmin)
                            ->minLength(12)
                            ->dehydrated(fn ($state) => filled($state)),
                        Forms\Components\TextInput::make('password_confirmation')
                            ->password()
                            ->required(fn ($livewire) => $livewire instanceof Pages\CreateAdmin)
                            ->same('password')
                            ->dehydrated(false),
                    ])
                    ->visible(fn ($livewire) => $livewire instanceof Pages\CreateAdmin),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label(__('system.active')),
                Tables\Columns\IconColumn::make('two_factor_enabled')
                    ->boolean()
                    ->label(__('system.2fa')),
                Tables\Columns\TextColumn::make('last_login_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('failed_login_attempts')
                    ->label(__('system.failed_attempts')),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('system.active')),
                Tables\Filters\TernaryFilter::make('two_factor_enabled')
                    ->label(__('system.2fa_enabled')),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListAdmins::route('/'),
            'create' => Pages\CreateAdmin::route('/create'),
            'edit' => Pages\EditAdmin::route('/{record}/edit'),
        ];
    }
}

