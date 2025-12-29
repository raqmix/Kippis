<div
    class="fi-topbar-right-cluster
           flex items-center gap-6
           px-4 h-full"
>
    <div class="flex items-center">
        @livewire(\App\Filament\Components\LanguageSwitcherTopbar::class, key('language-switcher-topbar'))
    </div>

    <div class="flex items-center">
        @livewire(\App\Filament\Components\NotificationsDropdown::class, key('notifications-dropdown-topbar'))
    </div>
</div>
