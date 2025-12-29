@php
    $notifications = $this->notifications;
    $unreadCount = $this->unreadCount;
@endphp

<div class="fi-topbar-item facebook-notifications" x-data="{ open: false }" @click.away="open = false" wire:poll.30s="loadUnreadCount">
    <!-- Notification Bell Button -->
    <button
        @click="open = !open"
        type="button"
        class="fi-topbar-item-button fi-topbar-item-button-label group relative flex items-center gap-x-2 rounded-lg px-3 py-2 text-sm font-medium outline-none transition-all duration-200 hover:bg-gray-100 focus:bg-gray-100 dark:hover:bg-white/10 dark:focus:bg-white/10"
        aria-label="{{ __('system.notifications') }}"
        title="{{ __('system.notifications') }}"
    >
        <x-filament::icon
            icon="heroicon-o-bell"
            class="h-5 w-5 transition-colors duration-200 group-hover:text-primary-600 dark:group-hover:text-primary-400"
        />
        @if($unreadCount > 0)
            <span class="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white shadow-lg ring-2 ring-white dark:ring-gray-900">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @endif
    </button>

    <!-- Facebook-Style Notification Dropdown -->
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="transform opacity-0 scale-95 translate-y-[-10px]"
        x-transition:enter-end="transform opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="transform opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="transform opacity-0 scale-95 translate-y-[-10px]"
        class="facebook-notification-dropdown absolute right-0 mt-3 w-[420px] origin-top-right rounded-lg bg-white shadow-2xl ring-1 ring-gray-200/50 focus:outline-none dark:bg-gray-900 dark:ring-gray-700/50 z-50 overflow-hidden"
        style="display: none;"
    >
        <!-- Header -->
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
            <div class="flex items-center justify-between">
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
        </div>
        
        <!-- Notifications List -->
        <div class="max-h-[500px] overflow-y-auto facebook-notification-scrollbar">
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
                        // No user name, show title as main text
                        $actionText = $title;
                        $userName = null; // Don't show user name section
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
                <div
                    wire:click="markAsRead('{{ $notification['id'] }}')"
                    class="facebook-notification-item group relative px-4 py-3 cursor-pointer transition-all duration-150 {{ !$isRead ? 'bg-[#E7F3FF] dark:bg-blue-900/20' : 'bg-white dark:bg-gray-900' }} hover:bg-[#F2F2F2] dark:hover:bg-gray-800/50"
                    @if($notification['actionUrl'])
                        onclick="window.location.href='{{ $notification['actionUrl'] }}'; $dispatch('close-notifications')"
                    @endif
                >
                    <div class="flex items-start gap-3">
                        <!-- Avatar (40px circular) -->
                        <div class="flex-shrink-0">
                            <img 
                                src="{{ $avatarUrl }}" 
                                alt="{{ $userName ?? 'System' }}"
                                class="h-10 w-10 rounded-full object-cover ring-1 ring-gray-200 dark:ring-gray-700"
                                onerror="this.src='https://ui-avatars.com/api/?name={{ urlencode($userName ?? 'System') }}&background=7B6CF6&color=fff&size=128&bold=true'"
                            />
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
                        
                        <!-- Right side: Unread indicator or Thumbnail -->
                        <div class="flex-shrink-0 flex items-start">
                            @if($thumbnailUrl)
                                <img 
                                    src="{{ $thumbnailUrl }}" 
                                    alt="Thumbnail"
                                    class="h-10 w-10 rounded object-cover"
                                    onerror="this.style.display='none'"
                                />
                            @elseif(!$isRead)
                                <span class="h-2 w-2 rounded-full bg-primary-500 mt-2"></span>
                            @endif
                        </div>
                    </div>
                </div>
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
        </div>
        
        <!-- Footer -->
        @if($notifications->count() > 0)
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 text-center">
                <a
                    href="{{ \App\Filament\Pages\AllNotifications::getUrl() }}"
                    class="text-sm font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 transition-colors duration-200"
                    @click="open = false"
                >
                    {{ __('system.view_all_notifications') }}
                </a>
            </div>
        @endif
    </div>
</div>
