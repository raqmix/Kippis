@php
    $notifications = $this->notifications;
    $unreadCount = $this->unreadCount;
    $currentLocale = app()->getLocale();
    $isRtl = $currentLocale === 'ar';
@endphp

<div class="fi-topbar-item" wire:poll.30s="loadUnreadCount">
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
            <div class="relative">
                <x-filament::icon-button
                    icon="heroicon-o-bell"
                    :label="__('system.notifications')"
                    color="gray"
                    size="lg"
                />
                @if($unreadCount > 0)
                    <x-filament::badge
                        color="danger"
                        size="sm"
                        class="absolute -top-1 -right-1 min-w-[1.25rem] h-5 px-1 flex items-center justify-center text-[10px] font-bold ring-2 ring-white dark:ring-gray-900"
                    >
                        {{ $unreadCount > 99 ? '99+' : $unreadCount }}
                    </x-filament::badge>
                @endif
            </div>
        </x-slot>

        <div {{ $isRtl ? 'dir="rtl"' : '' }} class="w-[420px] max-h-[600px] flex flex-col">
            <!-- Header -->
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                    {{ __('system.notifications') }}
                </h3>
                @if($unreadCount > 0)
                    <button
                        wire:click="markAllAsRead"
                        type="button"
                        class="text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 transition-colors duration-200 hover:underline"
                    >
                        {{ __('system.mark_all_as_read') }}
                    </button>
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
                                'class' => 'facebook-notification-item group relative px-4 py-3 cursor-pointer transition-all duration-150 ' . (!$isRead ? 'bg-[#E7F3FF] dark:bg-blue-900/20' : 'bg-white dark:bg-gray-900') . ' hover:bg-[#F2F2F2] dark:hover:bg-gray-800/50',
                            ]);
                            if (!empty($notification['actionUrl'])) {
                                $itemAttributes = $itemAttributes->merge(['href' => $notification['actionUrl']]);
                            }
                        @endphp
                        <x-filament::dropdown.list.item
                            wire:click="markAsRead('{{ $notification['id'] }}')"
                            :attributes="\Filament\Support\prepare_inherited_attributes($itemAttributes)"
                        >
                            <div class="flex items-start gap-3">
                                <!-- Unread dot indicator -->
                                @if(!$isRead)
                                    <div class="flex-shrink-0 mt-2">
                                        <span class="h-2 w-2 rounded-full bg-primary-500 block"></span>
                                    </div>
                                @else
                                    <div class="flex-shrink-0 w-2"></div>
                                @endif

                                <!-- Icon circle (avatar) -->
                                <div class="flex-shrink-0">
                                    <div class="h-10 w-10 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center overflow-hidden ring-1 ring-gray-200 dark:ring-gray-700">
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
                                                class="h-5 w-5 text-gray-500 dark:text-gray-400"
                                            />
                                        @endif
                                    </div>
                                </div>
                                
                                <!-- Notification Content -->
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-900 dark:text-white leading-relaxed">
                                        @if($userName)
                                            <span class="font-semibold">{{ $userName }}</span>
                                            <span class="text-gray-700 dark:text-gray-300">{{ $actionText }}</span>
                                        @else
                                            <span class="text-gray-900 dark:text-white">{{ $actionText }}</span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        {{ $notification['created_at']->diffForHumans() }}
                                    </p>
                                </div>
                                
                                <!-- Thumbnail (if available) -->
                                @if($thumbnailUrl)
                                    <div class="flex-shrink-0">
                                        <img 
                                            src="{{ $thumbnailUrl }}" 
                                            alt="Thumbnail"
                                            class="h-10 w-10 rounded object-cover"
                                            onerror="this.style.display='none'"
                                        />
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

            <!-- Footer -->
            @if($notifications->count() > 0)
                <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 flex items-center justify-between gap-2">
                    <a
                        href="{{ \App\Filament\Pages\AllNotifications::getUrl() }}"
                        class="text-sm font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 transition-colors duration-200"
                    >
                        عرض جميع الإشعارات
                    </a>
                    @if($unreadCount > 0)
                        <button
                            wire:click="markAllAsRead"
                            type="button"
                            class="text-sm font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 transition-colors duration-200"
                        >
                            وضع علامة على الكل كمقروء
                        </button>
                    @endif
                </div>
            @endif
        </div>
    </x-filament::dropdown>
</div>
