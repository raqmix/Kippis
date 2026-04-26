<x-filament-panels::page>
    <div
        x-data="{
            storeId: @entangle('selectedStoreId'),
            queue: @entangle('queue'),
            newOrderSound() {
                try {
                    const ctx = new (window.AudioContext || window.webkitAudioContext)();
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.frequency.value = 880;
                    osc.type = 'sine';
                    gain.gain.setValueAtTime(0.3, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.5);
                    osc.start(ctx.currentTime);
                    osc.stop(ctx.currentTime + 0.5);
                } catch(e) {}
            }
        }"
        class="space-y-6"
    >
        {{-- Store Selector --}}
        <div class="flex items-center gap-4">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Store:</label>
            <select
                wire:model.live="selectedStoreId"
                wire:change="loadQueue"
                class="block rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
            >
                @foreach($this->getStores() as $id => $name)
                    <option value="{{ $id }}" @selected($selectedStoreId == $id)>{{ $name }}</option>
                @endforeach
            </select>
            <button
                wire:click="loadQueue"
                class="inline-flex items-center gap-1 rounded-lg bg-primary-600 px-3 py-2 text-sm text-white hover:bg-primary-700"
            >
                <x-heroicon-o-arrow-path class="h-4 w-4" />
                Refresh
            </button>
        </div>

        {{-- Kanban Board --}}
        <div class="grid grid-cols-3 gap-4">
            @foreach(['confirmed' => 'Confirmed', 'preparing' => 'Preparing', 'ready' => 'Ready'] as $status => $label)
                <div class="rounded-xl border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900 p-3">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400">
                            {{ $label }}
                        </h3>
                        <span class="rounded-full bg-gray-200 dark:bg-gray-700 px-2 py-0.5 text-xs text-gray-700 dark:text-gray-300">
                            {{ count($queue[$status] ?? []) }}
                        </span>
                    </div>

                    <div class="space-y-2">
                        @forelse($queue[$status] ?? [] as $order)
                            <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 p-3 shadow-sm">
                                <div class="flex items-start justify-between">
                                    <span class="text-2xl font-black tracking-tight text-primary-600 dark:text-primary-400">
                                        {{ $order['pos_code'] }}
                                    </span>
                                    <span class="text-xs text-gray-400">
                                        {{ gmdate('i:s', $order['elapsed_seconds']) }}
                                    </span>
                                </div>

                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $order['customer_name'] }} &bull; {{ $order['item_count'] }} item(s)
                                </div>

                                <div class="mt-2 flex flex-wrap gap-1">
                                    @if($status === 'confirmed')
                                        <button
                                            wire:click="transition({{ $order['id'] }}, 'preparing')"
                                            class="rounded bg-yellow-500 px-2 py-0.5 text-xs text-white hover:bg-yellow-600"
                                        >
                                            → Preparing
                                        </button>
                                    @elseif($status === 'preparing')
                                        <button
                                            wire:click="transition({{ $order['id'] }}, 'ready')"
                                            class="rounded bg-green-500 px-2 py-0.5 text-xs text-white hover:bg-green-600"
                                        >
                                            → Ready
                                        </button>
                                    @elseif($status === 'ready')
                                        <button
                                            wire:click="transition({{ $order['id'] }}, 'picked_up')"
                                            class="rounded bg-blue-500 px-2 py-0.5 text-xs text-white hover:bg-blue-600"
                                        >
                                            ✓ Picked Up
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="py-4 text-center text-xs text-gray-400">No orders</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
