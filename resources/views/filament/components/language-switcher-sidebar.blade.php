@php
    $admin = auth('admin')->user();
    $currentLocale = app()->getLocale();
    $nextLocale = $currentLocale === 'en' ? 'ar' : 'en';
    $currentFlag = $currentLocale === 'en' ? '🇬🇧' : '🇪🇬';
    $nextFlag = $nextLocale === 'en' ? '🇬🇧' : '🇪🇬';
@endphp

<div class="fi-sidebar-nav-group">
    <ul class="fi-sidebar-nav-group-items space-y-1">
        <li>
            <a
                href="{{ request()->fullUrlWithQuery(['locale' => $nextLocale]) }}"
                class="fi-sidebar-nav-item-button group flex w-full items-center justify-center gap-x-2 rounded-lg px-3 py-2.5 text-sm font-medium outline-none transition duration-75 hover:bg-gray-50 focus:bg-gray-50 dark:hover:bg-white/5 dark:focus:bg-white/5"
                aria-label="{{ __('system.language') }}"
                title="{{ __('system.language') }}"
            >
                <span class="text-2xl transition-transform duration-200 group-hover:scale-110" role="img" aria-label="{{ $currentLocale === 'en' ? 'English' : 'Arabic' }}">
                    {{ $currentFlag }}
                </span>
            </a>
        </li>
    </ul>
</div>

