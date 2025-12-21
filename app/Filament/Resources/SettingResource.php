<?php

namespace App\Filament\Resources;

use App\Core\Models\Setting;
use App\Filament\Resources\SettingResource\Pages;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-cog-6-tooth';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.system_management');
    }

    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return __('navigation.settings');
    }

    public static function canViewAny(): bool
    {
        $user = auth()->guard('admin')->user();
        if (!$user) {
            return false;
        }
        
        // Clear cache to ensure fresh permission check
        app()['cache']->forget('spatie.permission.cache');
        
        return $user->can('manage_settings');
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSettings::route('/'),
        ];
    }
}

