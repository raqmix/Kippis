<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Sandbox Testing Section --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-actions-container flex items-center gap-x-3 p-6">
                <div class="grid gap-y-1 flex-1">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white flex items-center gap-2">
                        <svg class="h-5 w-5 text-yellow-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        {{ __('system.sandbox_mode') }}
                    </h3>
                    <p class="fi-section-header-description text-sm text-gray-500 dark:text-gray-400">
                        {{ __('system.test_sandbox_connection') }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <x-filament::button
                        wire:click="testSandbox"
                        :disabled="$isTesting"
                        color="warning"
                        icon="heroicon-o-arrow-path"
                    >
                        {{ __('system.test_connection') }}
                    </x-filament::button>
                </div>
            </div>

            <div class="fi-section-content-ctn divide-y divide-gray-100 dark:divide-white/10">
                <div class="fi-section-content p-6">
                    <div class="space-y-4">
                        {{-- Configuration Status --}}
                        @php
                            $sandboxConfig = $this->getSandboxConfigStatus();
                        @endphp
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('system.base_url') }}</div>
                                <div class="text-sm font-mono text-gray-900 dark:text-white mt-1">{{ $sandboxConfig['base_url'] }}</div>
                            </div>
                            <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('system.client_id') }}</div>
                                <div class="text-sm text-gray-900 dark:text-white mt-1 flex items-center gap-2">
                                    @if($sandboxConfig['client_id_set'])
                                        <span class="text-green-500">✓</span> {{ __('system.configured') }}
                                    @else
                                        <span class="text-red-500">✗</span> {{ __('system.not_configured') }}
                                    @endif
                                </div>
                            </div>
                            <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('system.client_secret') }}</div>
                                <div class="text-sm text-gray-900 dark:text-white mt-1 flex items-center gap-2">
                                    @if($sandboxConfig['client_secret_set'])
                                        <span class="text-green-500">✓</span> {{ __('system.configured') }}
                                    @else
                                        <span class="text-red-500">✗</span> {{ __('system.not_configured') }}
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Test Results --}}
                        @if($sandboxResult)
                            <div class="mt-4 p-4 rounded-lg {{ $sandboxResult['success'] ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800' : 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800' }}">
                                <div class="flex items-start gap-3">
                                    @if($sandboxResult['success'])
                                        <svg class="h-5 w-5 text-green-500 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    @else
                                        <svg class="h-5 w-5 text-red-500 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    @endif
                                    <div class="flex-1">
                                        <div class="font-semibold text-gray-900 dark:text-white">
                                            {{ $sandboxResult['success'] ? __('system.connection_successful') : __('system.connection_failed') }}
                                        </div>
                                        <div class="text-sm text-gray-600 dark:text-gray-300 mt-1">
                                            {{ $sandboxResult['message'] }}
                                        </div>
                                        @if(isset($sandboxResult['duration_ms']))
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                                {{ __('system.response_time') }}: {{ $sandboxResult['duration_ms'] }}ms
                                            </div>
                                        @endif
                                        @if(isset($sandboxResult['token_preview']))
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 font-mono">
                                                {{ __('system.token') }}: {{ $sandboxResult['token_preview'] }}
                                            </div>
                                        @endif
                                        @if(isset($sandboxResult['status_code']))
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                {{ __('system.status_code') }}: {{ $sandboxResult['status_code'] }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Live Testing Section --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-actions-container flex items-center gap-x-3 p-6">
                <div class="grid gap-y-1 flex-1">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white flex items-center gap-2">
                        <svg class="h-5 w-5 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0112 21 8.25 8.25 0 016.038 7.048 8.287 8.287 0 009 9.6a8.983 8.983 0 013.361-6.867 8.21 8.21 0 003 2.48z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 18a3.75 3.75 0 00.495-7.467 5.99 5.99 0 00-1.925 3.546 5.974 5.974 0 01-2.133-1A3.75 3.75 0 0012 18z" />
                        </svg>
                        {{ __('system.live_mode') }}
                    </h3>
                    <p class="fi-section-header-description text-sm text-gray-500 dark:text-gray-400">
                        {{ __('system.test_live_connection') }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <x-filament::button
                        wire:click="testLive"
                        :disabled="$isTesting"
                        color="danger"
                        icon="heroicon-o-arrow-path"
                    >
                        {{ __('system.test_connection') }}
                    </x-filament::button>
                </div>
            </div>

            <div class="fi-section-content-ctn divide-y divide-gray-100 dark:divide-white/10">
                <div class="fi-section-content p-6">
                    <div class="space-y-4">
                        {{-- Configuration Status --}}
                        @php
                            $liveConfig = $this->getLiveConfigStatus();
                        @endphp
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('system.base_url') }}</div>
                                <div class="text-sm font-mono text-gray-900 dark:text-white mt-1">{{ $liveConfig['base_url'] }}</div>
                            </div>
                            <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('system.client_id') }}</div>
                                <div class="text-sm text-gray-900 dark:text-white mt-1 flex items-center gap-2">
                                    @if($liveConfig['client_id_set'])
                                        <span class="text-green-500">✓</span> {{ __('system.configured') }}
                                    @else
                                        <span class="text-red-500">✗</span> {{ __('system.not_configured') }}
                                    @endif
                                </div>
                            </div>
                            <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('system.client_secret') }}</div>
                                <div class="text-sm text-gray-900 dark:text-white mt-1 flex items-center gap-2">
                                    @if($liveConfig['client_secret_set'])
                                        <span class="text-green-500">✓</span> {{ __('system.configured') }}
                                    @else
                                        <span class="text-red-500">✗</span> {{ __('system.not_configured') }}
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Test Results --}}
                        @if($liveResult)
                            <div class="mt-4 p-4 rounded-lg {{ $liveResult['success'] ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800' : 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800' }}">
                                <div class="flex items-start gap-3">
                                    @if($liveResult['success'])
                                        <svg class="h-5 w-5 text-green-500 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    @else
                                        <svg class="h-5 w-5 text-red-500 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    @endif
                                    <div class="flex-1">
                                        <div class="font-semibold text-gray-900 dark:text-white">
                                            {{ $liveResult['success'] ? __('system.connection_successful') : __('system.connection_failed') }}
                                        </div>
                                        <div class="text-sm text-gray-600 dark:text-gray-300 mt-1">
                                            {{ $liveResult['message'] }}
                                        </div>
                                        @if(isset($liveResult['duration_ms']))
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                                {{ __('system.response_time') }}: {{ $liveResult['duration_ms'] }}ms
                                            </div>
                                        @endif
                                        @if(isset($liveResult['token_preview']))
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 font-mono">
                                                {{ __('system.token') }}: {{ $liveResult['token_preview'] }}
                                            </div>
                                        @endif
                                        @if(isset($liveResult['status_code']))
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                {{ __('system.status_code') }}: {{ $liveResult['status_code'] }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-content p-6">
                <div class="flex justify-center">
                    <x-filament::button
                        wire:click="testBoth"
                        :disabled="$isTesting"
                        color="info"
                        size="lg"
                        icon="heroicon-o-arrow-path"
                    >
                        {{ __('system.test_both_connections') }}
                    </x-filament::button>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>

