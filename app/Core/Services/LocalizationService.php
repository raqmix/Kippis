<?php

namespace App\Core\Services;

use App\Core\Models\Admin;
use Illuminate\Support\Facades\App;

class LocalizationService
{
    public function setLocale(?Admin $admin = null): void
    {
        $locale = $admin?->locale ?? config('app.locale', 'en');
        App::setLocale($locale);
    }

    public function getAvailableLocales(): array
    {
        return [
            'en' => 'English',
            'ar' => 'Arabic',
        ];
    }
}
