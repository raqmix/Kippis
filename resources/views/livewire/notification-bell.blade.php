<div
    class="fi-topbar-item"
    x-data="{ open: false }"
    wire:poll.30s="loadNotifications"
>
    <button
        @click="open = !open"
        type="button"
        class="fi-topbar-item-button fi-topbar-item-button-label group relative flex items-center gap-x-2 rounded-lg px-3 py-2 text-sm font-medium outline-none transition-all duration-200 hover:bg-gray-50 focus:bg-gray-50 dark:hover:bg-white/5 dark:focus:bg-white/5 hover:scale-105"
        aria-label="{{ __('system.notifications') }}"
        title="{{ __('system.notifications') }}"
    >
        <x-filament::icon
            icon="heroicon-o-bell"
            class="h-5 w-5 transition-all duration-200 group-hover:text-primary-600 dark:group-hover:text-primary-400 group-hover:scale-110"
        />
        @if($unreadCount > 0)
            <span class="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-gradient-to-r from-danger-500 to-danger-600 text-[10px] font-bold text-white shadow-lg ring-2 ring-white dark:ring-gray-800 animate-pulse">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @endif
    </button>

    <div
        x-show="open"
        @click.away="open = false"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="transform opacity-0 scale-95 translate-y-[-10px]"
        x-transition:enter-end="transform opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="transform opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="transform opacity-0 scale-95 translate-y-[-10px]"
        class="absolute right-0 mt-3 w-[420px] origin-top-right rounded-xl bg-white shadow-2xl ring-1 ring-gray-200/50 focus:outline-none dark:bg-gray-900 dark:ring-gray-700/50 z-50 overflow-hidden"
        style="display: none;"
    >
        <!-- Header -->
        <div class="bg-gradient-to-r from-primary-50 to-primary-100/50 dark:from-primary-900/20 dark:to-primary-800/10 px-5 py-4 border-b border-gray-200/50 dark:border-gray-700/50">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <x-filament::icon
                        icon="heroicon-o-bell"
                        class="h-5 w-5 text-primary-600 dark:text-primary-400"
                    />
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">
                        {{ __('system.notifications') }}
                    </h3>
                    @if($unreadCount > 0)
                        <span class="inline-flex items-center rounded-full bg-primary-600 px-2 py-0.5 text-xs font-semibold text-white">
                            {{ $unreadCount }}
                        </span>
                    @endif
                </div>
                @if($unreadCount > 0)
                    <button
                        wire:click="markAllAsRead"
                        type="button"
                        class="text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 transition-colors duration-200 hover:underline"
                    >
                        {{ __('system.mark_all_read') }}
                    </button>
                @endif
            </div>
        </div>

        <!-- Notifications List -->
        <div class="max-h-[500px] overflow-y-auto custom-scrollbar">
            @if(empty($notifications))
                <div class="p-12 text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 mb-4">
                        <x-filament::icon
                            icon="heroicon-o-bell-slash"
                            class="h-8 w-8 text-gray-400 dark:text-gray-500"
                        />
                    </div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white mb-1">
                        {{ __('system.no_notifications') }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ __('system.all_caught_up') }}
                    </p>
                </div>
            @else
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($notifications as $notification)
                        <div
                            class="group relative px-5 py-4 transition-all duration-200 hover:bg-gray-50/50 dark:hover:bg-gray-800/50 {{ !$notification['is_read'] ? 'bg-primary-50/30 dark:bg-primary-900/10 border-l-4 border-primary-500' : '' }}"
                            wire:key="notification-{{ $notification['id'] }}"
                        >
                            <div class="flex items-start gap-4">
                                <!-- Icon with colored background -->
                                <div class="flex-shrink-0 mt-0.5">
                                    @if($notification['type'] === 'ticket')
                                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-warning-100 dark:bg-warning-900/30 ring-2 ring-warning-200 dark:ring-warning-800/50">
                                            <x-filament::icon icon="heroicon-o-ticket" class="h-5 w-5 text-warning-600 dark:text-warning-400" />
                                        </div>
                                    @elseif($notification['type'] === 'security')
                                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-danger-100 dark:bg-danger-900/30 ring-2 ring-danger-200 dark:ring-danger-800/50">
                                            <x-filament::icon icon="heroicon-o-shield-exclamation" class="h-5 w-5 text-danger-600 dark:text-danger-400" />
                                        </div>
                                    @elseif($notification['type'] === 'admin')
                                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-info-100 dark:bg-info-900/30 ring-2 ring-info-200 dark:ring-info-800/50">
                                            <x-filament::icon icon="heroicon-o-user" class="h-5 w-5 text-info-600 dark:text-info-400" />
                                        </div>
                                    @else
                                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-800 ring-2 ring-gray-200 dark:ring-gray-700">
                                            <x-filament::icon icon="heroicon-o-information-circle" class="h-5 w-5 text-gray-600 dark:text-gray-400" />
                                        </div>
                                    @endif
                                </div>

                                <!-- Content -->
                                <div class="flex-1 min-w-0 space-y-1.5">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="text-sm font-semibold leading-5 text-gray-900 dark:text-white">
                                            {{ $notification['title'] }}
                                        </p>
                                        @if(!$notification['is_read'])
                                            <span class="flex-shrink-0 h-2 w-2 rounded-full bg-primary-500 mt-1.5"></span>
                                        @endif
                                    </div>
                                    <p class="text-sm leading-5 text-gray-600 dark:text-gray-300 line-clamp-2">
                                        {{ $notification['message'] }}
                                    </p>
                                    <div class="flex items-center justify-between pt-1">
                                        <p class="text-xs font-medium text-gray-400 dark:text-gray-500">
                                            {{ $notification['created_at'] }}
                                        </p>
                                        @if(!empty($notification['action_url']))
                                            <a
                                                href="{{ $notification['action_url'] }}"
                                                class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 transition-colors duration-200 hover:underline"
                                                @click="open = false"
                                            >
                                                {{ $notification['action_text'] ?? __('system.view') }}
                                                <x-filament::icon icon="heroicon-o-arrow-right" class="h-3 w-3" />
                                            </a>
                                        @endif
                                    </div>
                                </div>

                                <!-- Mark as read button -->
                                @if(!$notification['is_read'])
                                    <button
                                        wire:click="markAsRead({{ $notification['id'] }})"
                                        type="button"
                                        class="flex-shrink-0 mt-0.5 rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-primary-600 dark:hover:bg-gray-800 dark:hover:text-primary-400 transition-all duration-200 opacity-0 group-hover:opacity-100"
                                        title="{{ __('system.mark_as_read') }}"
                                    >
                                        <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5" />
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Footer -->
        @if(!empty($notifications))
            <div class="bg-gray-50/50 dark:bg-gray-800/50 border-t border-gray-200/50 dark:border-gray-700/50 px-5 py-3">
                <a
                    href="{{ \App\Filament\Pages\AllNotifications::getUrl() }}"
                    class="flex items-center justify-center gap-2 text-sm font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 transition-colors duration-200"
                    @click="open = false"
                >
                    {{ __('system.view_all_notifications') }}
                    <x-filament::icon icon="heroicon-o-arrow-right" class="h-4 w-4" />
                </a>
            </div>
        @endif
    </div>

    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 3px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #4b5563;
        }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #6b7280;
        }
    </style>
</div>
