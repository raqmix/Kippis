@php
    $admin = auth('admin')->user();
    $unreadCount = $admin ? $admin->unreadNotificationsCount() : 0;
    $notifications = $admin ? $admin->unreadNotifications()->limit(10)->get() : collect();
@endphp

<div class="fi-topbar-item" x-data="{ open: false }">
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
            <span class="absolute top-1 right-1 flex h-4 w-4 items-center justify-center rounded-full bg-danger-600 text-xs font-semibold text-white">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @endif
    </button>

    <div
        x-show="open"
        @click.away="open = false"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="absolute right-0 mt-2 w-96 origin-top-right rounded-lg bg-white shadow-xl ring-1 ring-black ring-opacity-5 focus:outline-none dark:bg-gray-800 dark:ring-white/10 z-50"
        style="display: none;"
    >
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ __('system.notifications') }}
                </h3>
                @if($unreadCount > 0)
                    <form method="POST" action="{{ route('filament.admin.notifications.mark-all-read') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400">
                            {{ __('system.mark_all_read') }}
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <div class="max-h-96 overflow-y-auto">
            @if($notifications->isEmpty())
                <div class="p-8 text-center">
                    <x-filament::icon
                        icon="heroicon-o-bell-slash"
                        class="mx-auto h-12 w-12 text-gray-400"
                    />
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        {{ __('system.no_notifications') }}
                    </p>
                </div>
            @else
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($notifications as $notification)
                        <div class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0">
                                    @if($notification->type->value === 'ticket')
                                        <x-filament::icon icon="heroicon-o-ticket" class="h-5 w-5 text-primary-600" />
                                    @elseif($notification->type->value === 'security')
                                        <x-filament::icon icon="heroicon-o-shield-exclamation" class="h-5 w-5 text-danger-600" />
                                    @elseif($notification->type->value === 'admin')
                                        <x-filament::icon icon="heroicon-o-user" class="h-5 w-5 text-info-600" />
                                    @else
                                        <x-filament::icon icon="heroicon-o-information-circle" class="h-5 w-5 text-gray-600" />
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $notification->title }}
                                    </p>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $notification->message }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                        {{ $notification->created_at->diffForHumans() }}
                                    </p>
                                    @if($notification->action_url)
                                        <a href="{{ $notification->action_url }}" class="mt-2 inline-block text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400">
                                            {{ $notification->action_text ?? __('system.view') }}
                                        </a>
                                    @endif
                                </div>
                                    @if(!$notification->isRead())
                                        <form method="POST" action="{{ route('filament.admin.notifications.mark-read', $notification) }}" class="flex-shrink-0">
                                            @csrf
                                            <button type="submit" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" title="{{ __('system.mark_as_read') }}">
                                                <x-filament::icon icon="heroicon-o-check" class="h-4 w-4" />
                                            </button>
                                        </form>
                                    @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        @if($notifications->isNotEmpty())
            <div class="p-4 border-t border-gray-200 dark:border-gray-700 text-center">
                <a href="{{ \Filament\Facades\Filament::getUrl(\App\Filament\Pages\AllNotifications::class) }}" class="text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400">
                    {{ __('system.view_all_notifications') }}
                </a>
            </div>
        @endif
    </div>
</div>

