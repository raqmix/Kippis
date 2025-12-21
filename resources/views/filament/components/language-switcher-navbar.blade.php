@php
    $admin = auth('admin')->user();
    $currentLocale = app()->getLocale();
    $localizationService = app(\App\Core\Services\LocalizationService::class);
    $locales = $localizationService->getAvailableLocales();
    $currentLanguageName = $locales[$currentLocale] ?? 'Language';
@endphp

<div class="fi-topbar-item">
    <div x-data="{ open: false }" class="relative">
        <button
            @click="open = !open"
            type="button"
            class="fi-topbar-item-button fi-topbar-item-button-label group flex items-center gap-x-2 rounded-lg px-3 py-2 text-sm font-medium outline-none transition duration-75 hover:bg-gray-50 focus:bg-gray-50 dark:hover:bg-white/5 dark:focus:bg-white/5"
            aria-label="{{ __('system.language') }}"
            title="{{ __('system.language') }}"
        >
            <x-filament::icon
                icon="heroicon-o-language"
                class="h-5 w-5 transition-colors duration-75 group-hover:text-primary-600 dark:group-hover:text-primary-400"
            />
            <span class="hidden sm:block font-medium">{{ $currentLanguageName }}</span>
            <x-filament::icon
                icon="heroicon-o-chevron-down"
                class="h-4 w-4 transition-transform duration-75"
                ::class="{ 'rotate-180': open }"
            />
        </button>

        <div
            x-show="open"
            @click.away="open = false"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="transform opacity-0 scale-95"
            x-transition:enter-end="transform opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="transform opacity-100 scale-100"
            x-transition:leave-end="transform opacity-0 scale-95"
            class="absolute right-0 mt-2 w-56 origin-top-right rounded-lg bg-white shadow-xl ring-1 ring-black ring-opacity-5 focus:outline-none dark:bg-gray-800 dark:ring-white/10 z-50"
            style="display: none;"
        >
            <div class="py-1.5" role="menu" aria-orientation="vertical">
                @foreach($locales as $code => $name)
                    <a
                        href="{{ request()->fullUrlWithQuery(['locale' => $code]) }}"
                        class="flex items-center justify-between px-4 py-2.5 text-sm transition-all duration-75 {{ $currentLocale === $code ? 'bg-primary-50 text-primary-600 dark:bg-primary-900/20 dark:text-primary-400 font-medium' : 'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700/50' }}"
                        role="menuitem"
                    >
                        <div class="flex items-center gap-x-3">
                            @if($code === 'en')
                                <span class="text-lg" role="img" aria-label="English">🇬🇧</span>
                            @elseif($code === 'ar')
                                <span class="text-lg" role="img" aria-label="Arabic">🇸🇦</span>
                            @endif
                            <span class="font-medium">{{ $name }}</span>
                        </div>
                        @if($currentLocale === $code)
                            <x-filament::icon
                                icon="heroicon-o-check"
                                class="h-4 w-4 text-primary-600 dark:text-primary-400"
                            />
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</div>
