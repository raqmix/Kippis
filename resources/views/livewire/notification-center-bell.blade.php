<div
    class="fi-topbar-item relative"
    x-data="{ open: false }"
    wire:poll.30s="loadNotifications"
    dir="rtl"
>
    <!-- Bell Icon Button -->
    <button
        @click="open = !open"
        type="button"
        class="fi-topbar-item-button fi-topbar-item-button-label group relative flex items-center justify-center rounded-lg p-2 text-sm font-medium outline-none transition-all duration-200 hover:bg-gray-50 focus:bg-gray-50 dark:hover:bg-white/5 dark:focus:bg-white/5"
        aria-label="الإشعارات"
        title="الإشعارات"
    >
        <x-filament::icon
            icon="heroicon-o-bell"
            class="h-5 w-5 text-gray-600 dark:text-gray-400 transition-colors group-hover:text-primary-600 dark:group-hover:text-primary-400"
        />
        @if($unreadCount > 0)
            <span class="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white shadow-lg ring-2 ring-white dark:ring-gray-900">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @endif
    </button>

    <!-- Dropdown Panel -->
    <div
        x-show="open"
        @click.away="open = false"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute right-0 top-full mt-2 w-[420px] max-w-[90vw] rounded-xl bg-white shadow-xl ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10 z-[9999] overflow-hidden"
        style="display: none;"
        dir="rtl"
    >
        <!-- Header -->
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                الإشعارات
            </h3>
            <button
                type="button"
                class="text-gray-400 hover:text-gray-600 dark:text-gray-400 dark:hover:text-gray-300 transition-colors p-1 rounded"
                aria-label="الإعدادات"
            >
                <x-filament::icon
                    icon="heroicon-o-cog-6-tooth"
                    class="h-5 w-5"
                />
            </button>
        </div>

        <!-- Notifications List -->
        <div class="max-h-[500px] overflow-y-auto">
            @if(empty($notifications))
                <div class="flex flex-col items-center justify-center p-12 text-center">
                    <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                        <x-filament::icon
                            icon="heroicon-o-bell-slash"
                            class="h-8 w-8 text-gray-400 dark:text-gray-500"
                        />
                    </div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                        لا توجد إشعارات
                    </p>
                </div>
            @else
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($notifications as $notification)
                        <button
                            type="button"
                            wire:click="markAsRead({{ $notification['id'] }})"
                            wire:key="notification-{{ $notification['id'] }}"
                            class="group w-full px-5 py-4 text-right transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/50"
                        >
                            <div class="flex items-start gap-3">
                                <!-- Red dot for unread -->
                                @if(!$notification['is_read'])
                                    <div class="mt-2 flex-shrink-0">
                                        <span class="block h-2 w-2 rounded-full bg-red-500"></span>
                                    </div>
                                @else
                                    <div class="mt-2 flex-shrink-0 w-2"></div>
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

                                <!-- Content Block -->
                                <div class="min-w-0 flex-1 space-y-1">
                                    <p class="text-sm font-bold leading-5 text-gray-900 dark:text-white truncate">
                                        {{ $notification['title'] }}
                                    </p>
                                    <p class="text-sm leading-5 text-gray-600 dark:text-gray-300 line-clamp-2">
                                        {{ $notification['body'] }}
                                    </p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500">
                                        {{ $notification['arabic_time'] }}
                                    </p>
                                </div>
                            </div>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Footer -->
        @if(!empty($notifications))
            <div class="border-t border-gray-200 bg-gray-50/50 px-5 py-3 dark:border-gray-700 dark:bg-gray-800/50">
                <div class="flex items-center justify-between gap-3">
                    <a
                        href="{{ \App\Filament\Resources\NotificationCenterResource::getUrl('index') }}"
                        class="inline-flex items-center justify-center rounded-lg bg-success-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-success-700 dark:bg-success-500 dark:hover:bg-success-600"
                        @click="open = false"
                    >
                        عرض جميع الإشعارات
                    </a>
                    <button
                        wire:click="markAllAsRead"
                        type="button"
                        class="text-sm font-semibold text-success-600 transition-colors hover:text-success-700 dark:text-success-400 dark:hover:text-success-300"
                        @click="open = false"
                    >
                        وضع علامة على الكل كمقروء
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>
