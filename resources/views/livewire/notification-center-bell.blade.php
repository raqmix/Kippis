<div
    class="fi-topbar-item relative"
    x-data="{
        open: false,
        toggle() {
            this.open = !this.open;
            if (this.open) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        },
        close() {
            this.open = false;
            document.body.style.overflow = '';
        }
    }"
    wire:poll.30s="loadNotifications"
    dir="rtl"
>
    <!-- Bell Icon Button -->
    <button
        @click="toggle()"
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

    <!-- Backdrop Overlay -->
    <div
        x-show="open"
        @click="close()"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[9998]"
        style="display: none;"
    ></div>

    <!-- Sidebar Panel - Slides from Left -->
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="-translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        class="fixed left-0 top-0 h-full w-[420px] max-w-[85vw] bg-white shadow-2xl dark:bg-gray-800 z-[9999] overflow-hidden flex flex-col"
        style="display: none;"
        dir="rtl"
        @keydown.escape.window="close()"
    >
        <!-- Header -->
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700/50 bg-gradient-to-b from-gray-50/50 to-white dark:from-gray-800/50 dark:to-gray-800 flex-shrink-0">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                الإشعارات
            </h3>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="text-gray-400 hover:text-gray-600 dark:text-gray-400 dark:hover:text-gray-300 transition-all duration-200 p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700/50"
                    aria-label="الإعدادات"
                >
                    <x-filament::icon
                        icon="heroicon-o-cog-6-tooth"
                        class="h-4 w-4"
                    />
                </button>
                <button
                    @click="close()"
                    type="button"
                    class="text-gray-400 hover:text-gray-600 dark:text-gray-400 dark:hover:text-gray-300 transition-all duration-200 p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700/50"
                    aria-label="إغلاق"
                >
                    <x-filament::icon
                        icon="heroicon-o-x-mark"
                        class="h-5 w-5"
                    />
                </button>
            </div>
        </div>

        <!-- Notifications List -->
        <div class="flex-1 overflow-y-auto scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-transparent">
            @if(empty($notifications))
                <div class="flex flex-col items-center justify-center p-12 text-center">
                    <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-gray-100/80 dark:bg-gray-700/50 backdrop-blur-sm">
                        <x-filament::icon
                            icon="heroicon-o-bell-slash"
                            class="h-6 w-6 text-gray-400 dark:text-gray-500"
                        />
                    </div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                        لا توجد إشعارات
                    </p>
                </div>
            @else
                <div class="divide-y divide-gray-100/80 dark:divide-gray-700/50">
                    @foreach($notifications as $notification)
                        <button
                            type="button"
                            wire:click="markAsRead({{ $notification['id'] }})"
                            wire:key="notification-{{ $notification['id'] }}"
                            class="group w-full px-5 py-4 text-right transition-all duration-200 hover:bg-gray-50/80 dark:hover:bg-gray-700/30 active:bg-gray-100/80 dark:active:bg-gray-700/50"
                        >
                            <div class="flex items-start gap-3.5">
                                <!-- Red dot for unread -->
                                @if(!$notification['is_read'])
                                    <div class="mt-2 flex-shrink-0">
                                        <span class="block h-2.5 w-2.5 rounded-full bg-red-500 shadow-sm ring-2 ring-red-100 dark:ring-red-900/30"></span>
                                    </div>
                                @else
                                    <div class="mt-2 flex-shrink-0 w-2.5"></div>
                                @endif

                                <!-- Icon with light green background -->
                                <div class="flex-shrink-0">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-green-100/80 dark:bg-green-900/20 backdrop-blur-sm shadow-sm ring-1 ring-green-200/50 dark:ring-green-800/30">
                                        <x-filament::icon 
                                            icon="{{ $notification['icon'] }}" 
                                            class="h-5 w-5 text-green-600 dark:text-green-400" 
                                        />
                                    </div>
                                </div>

                                <!-- Content Block -->
                                <div class="min-w-0 flex-1 space-y-1">
                                    <p class="text-sm font-semibold leading-5 text-gray-900 dark:text-white truncate group-hover:text-gray-950 dark:group-hover:text-gray-100 transition-colors">
                                        {{ $notification['title'] }}
                                    </p>
                                    <p class="text-sm leading-5 text-gray-600 dark:text-gray-300 line-clamp-2">
                                        {{ $notification['body'] }}
                                    </p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
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
            <div class="border-t border-gray-100/80 dark:border-gray-700/50 px-5 py-3.5 bg-gradient-to-b from-white to-gray-50/50 dark:from-gray-800 dark:to-gray-800/50 backdrop-blur-sm flex-shrink-0">
                <div class="flex items-center justify-between gap-3">
                    <a
                        href="{{ \App\Filament\Resources\NotificationCenterResource::getUrl('index') }}"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-success-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition-all duration-200 hover:bg-success-700 hover:shadow-md active:scale-[0.98] dark:bg-success-500 dark:hover:bg-success-600"
                        @click="close()"
                    >
                        عرض جميع الإشعارات
                    </a>
                    <button
                        wire:click="markAllAsRead"
                        type="button"
                        class="text-xs font-semibold text-success-600 transition-all duration-200 hover:text-success-700 hover:underline dark:text-success-400 dark:hover:text-success-300 px-2 py-1 rounded-lg hover:bg-success-50 dark:hover:bg-success-900/20"
                        @click="close()"
                    >
                        وضع علامة على الكل كمقروء
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>
