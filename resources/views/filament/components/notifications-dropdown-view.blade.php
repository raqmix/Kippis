@php
    $notifications  = $this->notifications;
    $unreadCount    = $this->unreadCount;
    $currentLocale  = app()->getLocale();
    $isRtl          = $currentLocale === 'ar';
@endphp

<div class="fi-topbar-item relative" wire:poll.30s="loadUnreadCount">
    <x-filament::dropdown
        placement="bottom-end"
        teleport="true"
        :attributes="
            \Filament\Support\prepare_inherited_attributes(new \Illuminate\View\ComponentAttributeBag([
                'class' => 'kippis-notifications-dropdown',
            ]))
        "
    >
        <x-slot name="trigger">
            <div class="relative inline-flex">
                <x-filament::icon-button
                    icon="heroicon-o-bell"
                    :label="__('system.notifications')"
                    color="gray"
                    size="lg"
                    class="rounded-xl hover:bg-gray-100 dark:hover:bg-white/5"
                />

                @if ($unreadCount > 0)
                    <span
                        class="absolute -top-1 -right-1 inline-flex h-[20px] min-w-[20px] items-center justify-center rounded-full
                               bg-red-600 px-1 text-[11px] font-bold leading-none text-white shadow-lg
                               ring-2 ring-white dark:ring-gray-900"
                    >
                        {{ $unreadCount > 99 ? '99+' : $unreadCount }}
                    </span>
                @endif
            </div>
        </x-slot>

        {{-- Panel --}}
        <div
            dir="{{ $isRtl ? 'rtl' : 'ltr' }}"
            class="w-[420px] max-w-[92vw] overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/5
                   dark:bg-gray-900"
        >
            {{-- Header --}}
            <div class="px-4 py-3 border-b border-gray-200/60 dark:border-gray-700/60">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary-50 dark:bg-primary-950/30">
                            <x-filament::icon icon="heroicon-o-bell" class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                        </div>

                        <div class="leading-tight">
                            <p class="text-sm font-bold text-gray-900 dark:text-white">
                                {{ __('system.notifications') }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $unreadCount > 0 ? __('system.unread_count', ['count' => $unreadCount]) : __('system.all_caught_up') }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        @if ($unreadCount > 0)
                            <button
                                wire:click="markAllAsRead"
                                type="button"
                                class="inline-flex items-center gap-1 rounded-xl bg-primary-600 px-3 py-2 text-xs font-bold text-white
                                       shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500/40
                                       dark:bg-primary-600 dark:hover:bg-primary-500"
                            >
                                <x-filament::icon icon="heroicon-o-check-circle" class="h-4 w-4" />
                                <span>{{ __('system.mark_all_as_read') }}</span>
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            {{-- List --}}
            <div class="max-h-[520px] overflow-y-auto kippis-notification-scrollbar">
                <div class="p-2">
                    <x-filament::dropdown.list class="!shadow-none !ring-0">
                        @forelse ($notifications as $notification)
                            @php
                                $isRead = ! empty($notification['read_at']);
                                $title  = $notification['title'] ?? 'Notification';
                                $body   = $notification['body'] ?? '';
                                $icon   = $notification['icon'] ?? 'heroicon-o-bell';
                                $time   = $notification['created_at']?->diffForHumans();
                            @endphp

                            <x-filament::dropdown.list.item
                                wire:click="markAsRead('{{ $notification['id'] }}')"
                                :attributes="
                                    \Filament\Support\prepare_inherited_attributes(new \Illuminate\View\ComponentAttributeBag([
                                        'class' =>
                                            'group rounded-xl px-3 py-3 transition-all ' .
                                            'hover:bg-gray-50 dark:hover:bg-white/5 ' .
                                            'focus:bg-gray-50 dark:focus:bg-white/5 ' .
                                            (!$isRead ? 'bg-primary-50/30 dark:bg-primary-950/15' : ''),
                                    ]))
                                "
                            >
                                <div class="flex items-start gap-3">
                                    {{-- Unread dot --}}
                                    <div class="mt-2 w-3 shrink-0">
                                        @if (! $isRead)
                                            <span class="block h-2 w-2 rounded-full bg-red-500 shadow-sm"></span>
                                        @endif
                                    </div>

                                    {{-- Icon circle --}}
                                    <div class="shrink-0">
                                        <div
                                            class="flex h-11 w-11 items-center justify-center rounded-2xl
                                                   bg-emerald-50 ring-1 ring-emerald-200/60
                                                   dark:bg-emerald-950/25 dark:ring-emerald-800/30
                                                   group-hover:shadow-sm"
                                        >
                                            <x-filament::icon
                                                :icon="$icon"
                                                class="h-5 w-5 text-emerald-600 dark:text-emerald-400"
                                            />
                                        </div>
                                    </div>

                                    {{-- Content --}}
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-3">
                                            <p class="min-w-0 truncate text-sm font-bold text-gray-900 dark:text-white">
                                                {{ $title }}
                                            </p>

                                            @if ($time)
                                                <span class="shrink-0 text-[10px] font-medium text-gray-400 dark:text-gray-500">
                                                    {{ $time }}
                                                </span>
                                            @endif
                                        </div>

                                        @if ($body)
                                            <p class="mt-0.5 line-clamp-2 text-xs leading-relaxed text-gray-600 dark:text-gray-400">
                                                {{ $body }}
                                            </p>
                                        @endif

                                        {{-- subtle read status --}}
                                        <div class="mt-2 flex items-center gap-2">
                                            @if ($isRead)
                                                <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-gray-400 dark:text-gray-500">
                                                    <x-filament::icon icon="heroicon-o-check" class="h-3 w-3" />
                                                    <span>{{ __('system.read') }}</span>
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-primary-600 dark:text-primary-400">
                                                    <x-filament::icon icon="heroicon-o-sparkles" class="h-3 w-3" />
                                                    <span>{{ __('system.new') }}</span>
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </x-filament::dropdown.list.item>

                        @empty
                            <div class="px-6 py-14 text-center">
                                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 dark:bg-gray-800">
                                    <x-filament::icon icon="heroicon-o-bell-slash" class="h-7 w-7 text-gray-400 dark:text-gray-500" />
                                </div>
                                <p class="text-sm font-bold text-gray-900 dark:text-white">
                                    {{ __('system.no_notifications') }}
                                </p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ __('system.all_caught_up') }}
                                </p>
                            </div>
                        @endforelse
                    </x-filament::dropdown.list>
                </div>
            </div>

            {{-- Footer --}}
            @if ($notifications->count() > 0)
                <div class="border-t border-gray-200/60 dark:border-gray-700/60 bg-gray-50/60 dark:bg-gray-800/40">
                    <div class="px-4 py-3 flex items-center justify-between gap-3">
                        <a
                            href="{{ \App\Filament\Pages\AllNotifications::getUrl() }}"
                            class="inline-flex items-center gap-2 rounded-xl bg-white px-3 py-2 text-xs font-bold text-gray-900
                                   shadow-sm ring-1 ring-gray-200/70 hover:bg-gray-50
                                   dark:bg-gray-900 dark:text-white dark:ring-gray-700/60 dark:hover:bg-white/5"
                        >
                            <x-filament::icon
                                icon="{{ $isRtl ? 'heroicon-o-arrow-right' : 'heroicon-o-arrow-left' }}"
                                class="h-4 w-4"
                            />
                            <span>{{ __('system.view_all_notifications') }}</span>
                        </a>

                        <button
                            wire:click="markAllAsRead"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-xs font-semibold
                                   text-gray-600 hover:text-gray-900 hover:bg-white
                                   dark:text-gray-400 dark:hover:text-white dark:hover:bg-white/5"
                        >
                            <x-filament::icon icon="heroicon-o-check-circle" class="h-4 w-4" />
                            <span>{{ __('system.mark_all_as_read') }}</span>
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </x-filament::dropdown>

    {{-- Optional: nicer scrollbar for this dropdown only --}}
    <style>
        .kippis-notification-scrollbar::-webkit-scrollbar { width: 8px; }
        .kippis-notification-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .kippis-notification-scrollbar::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, .35); border-radius: 999px; }
        .kippis-notification-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(148, 163, 184, .55); }
        .dark .kippis-notification-scrollbar::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, .22); }
        .dark .kippis-notification-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(148, 163, 184, .35); }
    </style>
</div>
