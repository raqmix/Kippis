<?php

namespace App\Filament\Resources\LoyaltyWalletResource\Pages;

use App\Filament\Resources\LoyaltyWalletResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewLoyaltyWallet extends ViewRecord
{
    protected static string $resource = LoyaltyWalletResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('manual_adjustment')
                ->label(__('system.manual_adjustment'))
                ->icon('heroicon-o-adjustments-horizontal')
                ->visible(fn () => \Illuminate\Support\Facades\Gate::forUser(auth()->guard('admin')->user())->allows('manage_loyalty'))
                ->form([
                    \Filament\Forms\Components\Select::make('type')
                        ->label(__('system.type'))
                        ->options([
                            'add' => __('system.add_points'),
                            'reduce' => __('system.reduce_points'),
                        ])
                        ->required()
                        ->reactive(),
                    \Filament\Forms\Components\TextInput::make('points')
                        ->label(__('system.points'))
                        ->numeric()
                        ->required()
                        ->minValue(1),
                    \Filament\Forms\Components\Textarea::make('description')
                        ->label(__('system.description'))
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    $points = (int) $data['points'];
                    $type = $data['type'] === 'add' ? 'adjusted' : 'adjusted';
                    $pointsValue = $data['type'] === 'add' ? $points : -$points;

                    $this->record->addPoints(
                        $pointsValue,
                        $type,
                        $data['description'],
                        null,
                        null,
                        auth()->guard('admin')->id()
                    );

                    \Filament\Notifications\Notification::make()
                        ->title(__('system.points_adjusted'))
                        ->success()
                        ->send();
                }),
        ];
    }
}

