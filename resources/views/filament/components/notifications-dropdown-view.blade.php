@php
    $notifications = $this->notifications;
    $unreadCount = $this->unreadCount;
@endphp

<div class="fi-topbar-item" x-data="{ open: false }" @click.away="open = false">
    <button
        @click="open = !open"
        type="button"
        class="fi-topbar-item-button fi-topbar-item-button-label group relative flex items-center gap-x-2 rounded-lg px-3 py-2 text-sm font-medium outline-none transition duration-75 hover:bg-gray-50 focus:bg-gray-50 dark:hover:bg-white/5 dark:focus:bg-white/5"
        aria-label="{{ __('system.notifications') }}"
        title="{{ __('system.notifications') }}"
    >
        <x-filament::icon
            icon="heroicon-o-bell"
            class="h-5 w-5 transition-colors duration-75 group-hover:text-primary-600 dark:group-hover:text-primary-400"
        />
        @if($unreadCount > 0)
            <span class="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-danger-600 text-xs font-semibold text-white">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @endif
    </button>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="absolute right-0 mt-2 w-80 origin-top-right rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none dark:bg-gray-800 dark:ring-white/10 z-50"
        style="display: none;"
    >
        <div class="max-h-96 overflow-y-auto">
            <div class="border-b border-gray-200 dark:border-gray-700 px-4 py-3 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                    {{ __('system.notifications') }}
                </h3>
                @if($unreadCount > 0)
                    <button
                        wire:click="markAllAsRead"
                        class="text-xs text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                    >
                        {{ __('system.mark_all_as_read') }}
                    </button>
                @endif
            </div>
            
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($notifications as $notification)
                    @php
                        $isRead = $notification['read_at'] !== null;
                    @endphp
                    <div
                        wire:click="markAsRead('{{ $notification['id'] }}')"
                        class="px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer transition-colors {{ $isRead ? 'opacity-60' : 'bg-primary-50/50 dark:bg-primary-900/10' }}"
                        @if($notification['actionUrl'])
                            onclick="window.location.href='{{ $notification['actionUrl'] }}'"
                        @endif
                    >
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0">
                                @php
    $iconColor = $notification['iconColor'] ?? 'primary';
    $iconClasses = match($iconColor) {
        'success' => 'text-success-600 dark:text-success-400',
        'warning' => 'text-warning-600 dark:text-warning-400',
        'danger' => 'text-danger-600 dark:text-danger-400',
        'info' => 'text-info-600 dark:text-info-400',
        default => 'text-primary-600 dark:text-primary-400',
    };
@endphp
                                <x-filament::icon
                                    icon="{{ $notification['icon'] }}"
                                    class="h-5 w-5 {{ $iconClasses }}"
                                />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $notification['title'] }}
                                </p>
                                @if($notification['body'])
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        {{ $notification['body'] }}
                                    </p>
                                @endif
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                    {{ $notification['created_at']->diffForHumans() }}
                                </p>
                            </div>
                            @if(!$isRead)
                                <div class="flex-shrink-0">
                                    <span class="h-2 w-2 rounded-full bg-primary-600"></span>
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center">
                        <x-filament::icon
                            icon="heroicon-o-bell-slash"
                            class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500"
                        />
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            {{ __('system.no_notifications') }}
                        </p>
                    </div>
                @endforelse
            </div>
            
            @if($notifications->count() > 0)
                <div class="border-t border-gray-200 dark:border-gray-700 px-4 py-3 text-center">
                    <a
                        href="{{ \App\Filament\Pages\AllNotifications::getUrl() }}"
                        class="text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 font-medium"
                    >
                        {{ __('system.view_all_notifications') }}
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>

