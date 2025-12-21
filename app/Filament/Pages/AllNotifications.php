<?php

namespace App\Filament\Pages;

use App\Core\Models\AdminNotification;
use App\Core\Services\NotificationService;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class AllNotifications extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.all-notifications';
    
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-bell';
    }
    
    protected static ?int $navigationSort = 999;
    
    public static function getNavigationLabel(): string
    {
        return __('navigation.all_notifications');
    }

    public static function getNavigationGroup(): ?string
    {
        return null; // Don't show in sidebar, accessed via bell icon
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false; // Hide from sidebar
    }

    public function table(Table $table): Table
    {
        $admin = Auth::guard('admin')->user();
        
        return $table
            ->query(AdminNotification::query()->where('admin_id', $admin->id))
            ->columns([
                Tables\Columns\IconColumn::make('read_at')
                    ->label(__('system.read'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                Tables\Columns\TextColumn::make('type')
                    ->label(__('system.type'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ticket' => 'warning',
                        'security' => 'danger',
                        'admin' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('title')
                    ->label(__('system.title'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('message')
                    ->label(__('system.message'))
                    ->limit(50)
                    ->wrap(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('system.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('read_at')
                    ->label(__('system.read'))
                    ->placeholder(__('system.all'))
                    ->trueLabel(__('system.read'))
                    ->falseLabel(__('system.unread')),
                Tables\Filters\SelectFilter::make('type')
                    ->label(__('system.type'))
                    ->options([
                        'system' => __('system.system'),
                        'admin' => __('system.admin'),
                        'ticket' => __('system.ticket'),
                        'security' => __('system.security'),
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('mark_read')
                    ->label(__('system.mark_as_read'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->action(function (AdminNotification $record) {
                        app(NotificationService::class)->markAsRead($record);
                        notify()->success(__('system.notification_marked_as_read'));
                    })
                    ->visible(fn (AdminNotification $record) => !$record->isRead()),
                Tables\Actions\Action::make('view')
                    ->label(__('system.view'))
                    ->icon('heroicon-o-eye')
                    ->url(fn (AdminNotification $record) => $record->action_url)
                    ->visible(fn (AdminNotification $record) => $record->action_url !== null),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('mark_read')
                        ->label(__('system.mark_as_read'))
                        ->icon('heroicon-o-check')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                app(NotificationService::class)->markAsRead($record);
                            }
                            notify()->success(__('system.notifications_marked_as_read'));
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
