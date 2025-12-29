@php
    $notifications = $this->notifications;
    $unreadCount = $this->unreadCount;
    $currentLocale = app()->getLocale();
    $isRtl = $currentLocale === 'ar';
@endphp

<div class="fi-topbar-item relative" wire:poll.30s="loadUnreadCount">
    <x-filament::dropdown
        placement="bottom-end"
        teleport="true"
        :attributes="
            \Filament\Support\prepare_inherited_attributes(new \Illuminate\View\ComponentAttributeBag([
                'class' => 'facebook-notifications-dropdown',
            ]))
        "
    >
        <x-slot name="trigger">
            <div class="relative inline-block">
                <x-filament::icon-button
                    icon="heroicon-o-bell"
                    :label="__('system.notifications')"
                    color="gray"
                    size="lg"
                />
                @if($unreadCount > 0)
                    <span class="absolute -top-1 -right-1 flex h-[18px] w-[18px] items-center justify-center rounded-full bg-gradient-to-br from-red-500 to-red-600 text-[10px] font-bold leading-none text-white shadow-lg ring-2 ring-white dark:ring-gray-900 z-10">
                        {{ $unreadCount > 99 ? '99+' : $unreadCount }}
                    </span>
                @endif
            </div>
        </x-slot>

        <div {{ $isRtl ? 'dir="rtl"' : '' }} class="w-[420px] max-h-[600px] flex flex-col">
            <!-- Header -->
            <div class="px-5 py-4 border-b border-gray-200/60 dark:border-gray-700/60 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                    {{ __('system.notifications') }}
                </h3>
                @if($unreadCount > 0)
                    <x-filament::button
                        wire:click="markAllAsRead"
                        size="sm"
                        color="primary"
                        variant="ghost"
                    >
                        {{ __('system.mark_all_as_read') }}
                    </x-filament::button>
                @endif
            </div>

            <!-- Notifications List -->
            <div class="flex-1 overflow-y-auto facebook-notification-scrollbar max-h-[500px]">
                <x-filament::dropdown.list>
                    @forelse($notifications as $notification)
                        @php
                            $isRead = $notification['read_at'] !== null;
                            $userName = $notification['userName'] ?? null;
                            $userAvatar = $notification['userAvatar'] ?? null;
                            $thumbnail = $notification['thumbnail'] ?? null;
                            
                            // Get notification text
                            $title = $notification['title'] ?? 'Notification';
                            $body = $notification['body'] ?? '';
                            
                            // If we have a user name, use body as the action text
                            // Otherwise, use title as the full message
                            if ($userName) {
                                $actionText = $body ?: $title;
                            } else {
                                $actionText = $title;
                                $userName = null;
                            }
                            
                            // Generate avatar URL or use default
                            $avatarUrl = $userAvatar 
                                ? (str_starts_with($userAvatar, 'http') ? $userAvatar : asset('storage/' . $userAvatar))
                                : ($userName ? 'https://ui-avatars.com/api/?name=' . urlencode($userName) . '&background=7B6CF6&color=fff&size=128&bold=true' : 'https://ui-avatars.com/api/?name=System&background=6B7280&color=fff&size=128&bold=true');
                            
                            // Generate thumbnail URL
                            $thumbnailUrl = $thumbnail 
                                ? (str_starts_with($thumbnail, 'http') ? $thumbnail : asset('storage/' . $thumbnail))
                                : null;
                        @endphp
                        @php
                            $itemAttributes = new \Illuminate\View\ComponentAttributeBag([
                                'class' => 'facebook-notification-item group relative px-5 py-4 cursor-pointer transition-all duration-200 ' . (!$isRead ? 'bg-gradient-to-r from-blue-50/80 via-blue-50/60 to-transparent dark:from-blue-900/30 dark:via-blue-900/20 dark:to-transparent' : 'bg-white dark:bg-gray-900') . ' hover:shadow-sm',
                            ]);
                            if (!empty($notification['actionUrl'])) {
                                $itemAttributes = $itemAttributes->merge(['href' => $notification['actionUrl']]);
                            }
                        @endphp
                        <x-filament::dropdown.list.item
                            wire:click="markAsRead('{{ $notification['id'] }}')"
                            :attributes="\Filament\Support\prepare_inherited_attributes($itemAttributes)"
                        >
                            <div class="flex items-start gap-4">
                                <!-- Unread dot indicator -->
                                @if(!$isRead)
                                    <div class="flex-shrink-0 mt-2.5">
                                        <span class="h-2.5 w-2.5 rounded-full bg-gradient-to-br from-primary-500 to-primary-600 block shadow-lg shadow-primary-500/50"></span>
                                    </div>
                                @else
                                    <div class="flex-shrink-0 w-2.5"></div>
                                @endif

                                <!-- Icon circle (avatar) - Enhanced -->
                                <div class="flex-shrink-0">
                                    <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-800 flex items-center justify-center overflow-hidden ring-2 ring-gray-200/50 dark:ring-gray-700/50 shadow-md transition-all duration-200 group-hover:ring-primary-300 dark:group-hover:ring-primary-600 group-hover:scale-105">
                                        @if($userAvatar || $userName)
                                            <img 
                                                src="{{ $avatarUrl }}" 
                                                alt="{{ $userName ?? 'System' }}"
                                                class="h-full w-full object-cover"
                                                onerror="this.src='https://ui-avatars.com/api/?name={{ urlencode($userName ?? 'System') }}&background=7B6CF6&color=fff&size=128&bold=true'"
                                            />
                                        @else
                                            <x-filament::icon
                                                icon="{{ $notification['icon'] ?? 'heroicon-o-bell' }}"
                                                class="h-6 w-6 text-gray-500 dark:text-gray-400"
                                            />
                                        @endif
                                    </div>
                                </div>
                                
                                <!-- Notification Content - Better Arrangement -->
                                <div class="flex-1 min-w-0 space-y-1">
                                    <p class="text-sm text-gray-900 dark:text-white leading-relaxed">
                                        @if($userName)
                                            <span class="font-bold text-gray-900 dark:text-white">{{ $userName }}</span>
                                            <span class="text-gray-600 dark:text-gray-300 ml-1">{{ $actionText }}</span>
                                        @else
                                            <span class="text-gray-900 dark:text-white font-medium">{{ $actionText }}</span>
                                        @endif
                                    </p>
                                    <div class="flex items-center gap-2">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                                            {{ $notification['created_at']->diffForHumans() }}
                                        </p>
                                        @if(!$isRead)
                                            <span class="h-1.5 w-1.5 rounded-full bg-primary-500"></span>
                                        @endif
                                    </div>
                                </div>
                                
                                <!-- Thumbnail (if available) - Enhanced -->
                                @if($thumbnailUrl)
                                    <div class="flex-shrink-0">
                                        <div class="h-12 w-12 rounded-lg overflow-hidden ring-2 ring-gray-200/50 dark:ring-gray-700/50 shadow-md">
                                            <img 
                                                src="{{ $thumbnailUrl }}" 
                                                alt="Thumbnail"
                                                class="h-full w-full object-cover transition-transform duration-200 group-hover:scale-110"
                                                onerror="this.style.display='none'"
                                            />
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </x-filament::dropdown.list.item>
                    @empty
                        <div class="px-6 py-16 text-center">
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
                    @endforelse
                </x-filament::dropdown.list>
            </div>

            <!-- Footer - Enhanced Design -->
            @if($notifications->count() > 0)
                <div class="px-5 py-4 border-t border-gray-200/60 dark:border-gray-700/60 bg-gradient-to-r from-gray-50/80 via-gray-50/60 to-transparent dark:from-gray-800/50 dark:via-gray-800/30 dark:to-transparent backdrop-blur-sm flex items-center justify-between gap-3">
                    <a
                        href="{{ \App\Filament\Pages\AllNotifications::getUrl() }}"
                        class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 transition-all duration-200 hover:bg-primary-50 dark:hover:bg-primary-900/20 group"
                    >
                        <span>عرض جميع الإشعارات</span>
                        <x-filament::icon
                            icon="heroicon-o-arrow-left"
                            class="h-4 w-4 transition-transform duration-200 group-hover:translate-x-0.5"
                        />
                    </a>
                    @if($unreadCount > 0)
                        <button
                            wire:click="markAllAsRead"
                            type="button"
                            class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 transition-all duration-200 hover:bg-primary-50 dark:hover:bg-primary-900/20"
                        >
                            <x-filament::icon
                                icon="heroicon-o-check-circle"
                                class="h-4 w-4"
                            />
                            <span>وضع علامة على الكل كمقروء</span>
                        </button>
                    @endif
                </div>
            @endif
        </div>
    </x-filament::dropdown>
</div>
