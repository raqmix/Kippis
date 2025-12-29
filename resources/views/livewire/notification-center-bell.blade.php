<div
    class="fi-topbar-item"
    x-data="{ open: false }"
    wire:poll.30s="loadNotifications"
    dir="rtl"
>
    <button
        @click="open = !open"
        type="button"
        class="fi-topbar-item-button fi-topbar-item-button-label group relative flex items-center gap-x-2 rounded-lg px-3 py-2 text-sm font-medium outline-none transition-all duration-200 hover:bg-gray-50 focus:bg-gray-50 dark:hover:bg-white/5 dark:focus:bg-white/5 hover:scale-105"
        aria-label="الإشعارات"
        title="الإشعارات"
    >
        <x-filament::icon
            icon="heroicon-o-bell"
            class="h-5 w-5 transition-all duration-200 group-hover:text-primary-600 dark:group-hover:text-primary-400 group-hover:scale-110"
        />
        @if($unreadCount > 0)
            <span class="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white shadow-lg ring-2 ring-white dark:ring-gray-800">
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
        class="absolute right-0 mt-3 w-[400px] origin-top-right rounded-xl bg-white shadow-lg ring-1 ring-gray-200/50 focus:outline-none dark:bg-gray-900 dark:ring-gray-700/50 z-50 overflow-hidden"
        style="display: none;"
        dir="rtl"
    >
        <!-- Header -->
        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                    الإشعارات
                </h3>
                <button
                    type="button"
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                    aria-label="الإعدادات"
                >
                    <x-filament::icon
                        icon="heroicon-o-cog-6-tooth"
                        class="h-5 w-5"
                    />
                </button>
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
                        لا توجد إشعارات
                    </p>
                </div>
            @else
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($notifications as $notification)
                        <div
                            class="group relative px-5 py-4 transition-all duration-200 hover:bg-gray-50/50 dark:hover:bg-gray-800/50 cursor-pointer"
                            wire:key="notification-{{ $notification['id'] }}"
                            wire:click="markAsRead({{ $notification['id'] }})"
                        >
                            <div class="flex items-start gap-4">
                                <!-- Red dot for unread -->
                                @if(!$notification['is_read'])
                                    <div class="flex-shrink-0 mt-2">
                                        <span class="flex h-2 w-2 rounded-full bg-red-500"></span>
                                    </div>
                                @else
                                    <div class="flex-shrink-0 mt-2 w-2"></div>
                                @endif

                                <!-- Icon with light green background -->
                                <div class="flex-shrink-0">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                                        <x-filament::icon 
                                            icon="{{ $notification['icon'] }}" 
                                            class="h-5 w-5 text-green-600 dark:text-green-400" 
                                        />
                                    </div>
                                </div>

                                <!-- Content -->
                                <div class="flex-1 min-w-0 space-y-1">
                                    <p class="text-sm font-bold leading-5 text-gray-900 dark:text-white">
                                        {{ $notification['title'] }}
                                    </p>
                                    <p class="text-sm leading-5 text-gray-600 dark:text-gray-300 line-clamp-2">
                                        {{ $notification['body'] }}
                                    </p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                        {{ $notification['arabic_time'] }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Footer -->
        @if(!empty($notifications))
            <div class="bg-gray-50/50 dark:bg-gray-800/50 border-t border-gray-200 dark:border-gray-700 px-5 py-3">
                <div class="flex items-center justify-between gap-3">
                    <a
                        href="{{ \App\Filament\Resources\NotificationCenterResource::getUrl('index') }}"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-success-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-success-700 dark:bg-success-500 dark:hover:bg-success-600"
                        @click="open = false"
                    >
                        عرض جميع الإشعارات
                    </a>
                    <button
                        wire:click="markAllAsRead"
                        type="button"
                        class="text-sm font-semibold text-success-600 hover:text-success-700 dark:text-success-400 dark:hover:text-success-300 transition-colors"
                        @click="open = false"
                    >
                        وضع علامة على الكل كمقروء
                    </button>
                </div>
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

