@php
    $locales = $this->availableLocales;
    $currentLocale = $this->locale ?? 'en';
    $otherLocale = $currentLocale === 'en' ? 'ar' : 'en';
    $otherLocaleName = $locales[$otherLocale] ?? ($otherLocale === 'ar' ? 'العربية' : 'English');
@endphp

<div class="fi-topbar-item">
    <button
        wire:click="switchLocale('{{ $otherLocale }}')"
        type="button"
        class="fi-topbar-item-button fi-topbar-item-button-label group flex items-center gap-x-2 rounded-lg px-3 py-2 text-sm font-medium outline-none transition duration-75 hover:bg-gray-50 focus:bg-gray-50 dark:hover:bg-white/5 dark:focus:bg-white/5"
        aria-label="{{ __('system.language') }}"
        title="{{ __('system.language') }}"
    >
        <x-filament::icon
            icon="heroicon-o-language"
            class="h-5 w-5 transition-colors duration-75 group-hover:text-primary-600 dark:group-hover:text-primary-400"
        />
        <span class="hidden sm:inline">{{ $otherLocaleName }}</span>
    </button>
</div>
