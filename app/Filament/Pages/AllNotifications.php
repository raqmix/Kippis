<?php

namespace App\Filament\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Notifications\DatabaseNotification;
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
            ->query($admin->notifications()->getQuery())
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
                    ->formatStateUsing(fn (DatabaseNotification $record) => $record->type)
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('title')
                    ->label(__('system.title'))
                    ->formatStateUsing(fn (DatabaseNotification $record) => $record->data['title'] ?? 'Notification')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('body')
                    ->label(__('system.message'))
                    ->formatStateUsing(fn (DatabaseNotification $record) => $record->data['body'] ?? '')
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
            ])
            ->actions([
                Actions\Action::make('mark_read')
                    ->label(__('system.mark_as_read'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->action(function (DatabaseNotification $record) {
                        if (!$record->read_at) {
                            $record->markAsRead();
                            Notification::make()
                                ->title(__('system.notification_marked_as_read'))
                                ->success()
                                ->send();
                        }
                    })
                    ->visible(fn (DatabaseNotification $record) => !$record->read_at),
                Actions\Action::make('view')
                    ->label(__('system.view'))
                    ->icon('heroicon-o-eye')
                    ->url(fn (DatabaseNotification $record) => $record->data['action_url'] ?? null)
                    ->visible(fn (DatabaseNotification $record) => !empty($record->data['action_url'] ?? null)),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('mark_read')
                        ->label(__('system.mark_as_read'))
                        ->icon('heroicon-o-check')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                if (!$record->read_at) {
                                    $record->markAsRead();
                                }
                            }
                            Notification::make()
                                ->title(__('system.notifications_marked_as_read'))
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
