<?php

namespace App\Filament\Resources;

use App\Core\Models\NotificationCenter;
use App\Filament\Resources\NotificationCenterResource\Pages;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class NotificationCenterResource extends Resource
{
    protected static ?string $model = NotificationCenter::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-bell';
    }

    public static function getNavigationLabel(): string
    {
        return 'الإشعارات';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.system_management');
    }

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return false; // Hidden from navigation
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make('معلومات الإشعار')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('المستخدم')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->placeholder('اختر مستخدم (اتركه فارغاً للإشعارات العامة)'),
                        Forms\Components\Select::make('type')
                            ->label('النوع')
                            ->options([
                                'new_booking' => 'حجوزات جديدة',
                                'low_stock' => 'مخزون منخفض',
                                'staff_absence' => 'غياب الموظفين',
                                'new_reviews' => 'مراجعات جديدة',
                            ])
                            ->required()
                            ->native(false),
                        Forms\Components\TextInput::make('title')
                            ->label('العنوان')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('body')
                            ->label('المحتوى')
                            ->required()
                            ->rows(4),
                        Forms\Components\TextInput::make('icon')
                            ->label('الأيقونة')
                            ->default('heroicon-o-bell')
                            ->placeholder('heroicon-o-bell'),
                        Forms\Components\TextInput::make('color')
                            ->label('اللون')
                            ->default('light-green')
                            ->placeholder('light-green'),
                        Forms\Components\TextInput::make('action_url')
                            ->label('رابط الإجراء')
                            ->url()
                            ->placeholder('/admin/resource'),
                        Forms\Components\Toggle::make('is_read')
                            ->label('مقروء')
                            ->default(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('is_read')
                    ->label('الحالة')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                Tables\Columns\TextColumn::make('type')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'new_booking' => 'حجوزات جديدة',
                        'low_stock' => 'مخزون منخفض',
                        'staff_absence' => 'غياب الموظفين',
                        'new_reviews' => 'مراجعات جديدة',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'new_booking' => 'success',
                        'low_stock' => 'warning',
                        'staff_absence' => 'info',
                        'new_reviews' => 'primary',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('body')
                    ->label('المحتوى')
                    ->searchable()
                    ->limit(100)
                    ->wrap(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('المستخدم')
                    ->searchable()
                    ->sortable()
                    ->placeholder('عام'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('read_at')
                    ->label('تاريخ القراءة')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->placeholder('غير مقروء'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_read')
                    ->label('الحالة')
                    ->options([
                        '1' => 'مقروء',
                        '0' => 'غير مقروء',
                    ]),
                Tables\Filters\SelectFilter::make('type')
                    ->label('النوع')
                    ->options([
                        'new_booking' => 'حجوزات جديدة',
                        'low_stock' => 'مخزون منخفض',
                        'staff_absence' => 'غياب الموظفين',
                        'new_reviews' => 'مراجعات جديدة',
                    ]),
            ])
            ->actions([
                Actions\Action::make('markAsRead')
                    ->label('وضع علامة كمقروء')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (NotificationCenter $record) => !$record->is_read)
                    ->action(fn (NotificationCenter $record) => $record->markAsRead())
                    ->requiresConfirmation(),
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('markAsRead')
                        ->label('وضع علامة كمقروء')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => $records->each->markAsRead())
                        ->requiresConfirmation(),
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotificationCenters::route('/'),
            'create' => Pages\CreateNotificationCenter::route('/create'),
            'view' => Pages\ViewNotificationCenter::route('/{record}'),
            'edit' => Pages\EditNotificationCenter::route('/{record}/edit'),
        ];
    }
}

