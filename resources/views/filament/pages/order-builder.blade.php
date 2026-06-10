<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- ============ LEFT: Product picker ============ --}}
        <div class="lg:col-span-2">
            <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                        {{ __('system.products') }}
                    </h2>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="{{ __('system.search') }}..."
                        class="fi-input block w-56 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                    />
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                    @forelse ($this->products as $product)
                        <div
                            draggable="true"
                            x-on:dragstart="$event.dataTransfer.setData('text/plain', '{{ $product['id'] }}'); $event.dataTransfer.effectAllowed = 'copy';"
                            wire:click="addToCart({{ $product['id'] }})"
                            class="group cursor-grab select-none rounded-xl border border-gray-200 bg-white p-3 transition hover:border-primary-400 hover:shadow-md active:cursor-grabbing dark:border-white/10 dark:bg-white/5"
                            title="{{ __('system.drag_or_click_to_add') }}"
                        >
                            <div class="mb-2 aspect-square overflow-hidden rounded-lg bg-gray-100 dark:bg-white/10">
                                @if ($product['image'])
                                    <img src="{{ $product['image'] }}" alt="{{ $product['name'] }}" class="h-full w-full object-cover" />
                                @else
                                    <div class="flex h-full w-full items-center justify-center text-gray-300">
                                        <x-heroicon-o-cube class="h-10 w-10" />
                                    </div>
                                @endif
                            </div>
                            <p class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $product['name'] }}</p>
                            <p class="text-xs text-primary-600 dark:text-primary-400">{{ number_format($product['price'], 2) }} EGP</p>
                        </div>
                    @empty
                        <p class="col-span-full py-8 text-center text-sm text-gray-500">{{ __('system.no_results') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ============ RIGHT: Cart / order ============ --}}
        <div class="lg:col-span-1">
            <div
                x-data="{ over: false }"
                x-on:dragover.prevent="over = true"
                x-on:dragleave.prevent="over = false"
                x-on:drop.prevent="over = false; $wire.addToCart(parseInt($event.dataTransfer.getData('text/plain')))"
                :class="over ? 'ring-2 ring-primary-500 ring-offset-2' : 'ring-1 ring-gray-950/5 dark:ring-white/10'"
                class="fi-section sticky top-4 rounded-xl bg-white p-4 shadow-sm transition dark:bg-gray-900"
            >
                <h2 class="mb-4 text-base font-semibold text-gray-950 dark:text-white">
                    {{ __('system.create_order') }}
                </h2>

                {{-- Order meta --}}
                <div class="mb-4 space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('system.store') }} *</label>
                        <select wire:model="storeId" class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white">
                            <option value="">—</option>
                            @foreach ($this->storeOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('system.channel') }}</label>
                        <select wire:model="channelId" class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white">
                            <option value="">—</option>
                            @foreach ($this->channelOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('system.customer') }}</label>
                        <select wire:model="customerId" class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white">
                            <option value="">{{ __('system.guest') }}</option>
                            @foreach ($this->customerOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Cart lines --}}
                <div class="min-h-[120px] space-y-3 border-t border-gray-100 pt-3 dark:border-white/10">
                    @forelse ($cart as $line)
                        <div wire:key="{{ $line['key'] }}" class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $line['name'] }}</p>
                                    <p class="text-xs text-gray-500">{{ number_format($line['unit_price'], 2) }} EGP</p>
                                </div>
                                <button type="button" wire:click="removeLine('{{ $line['key'] }}')" class="text-gray-400 hover:text-danger-500">
                                    <x-heroicon-m-x-mark class="h-4 w-4" />
                                </button>
                            </div>

                            {{-- Modifiers --}}
                            @if (! empty($line['available']))
                                <div class="mt-2 flex flex-wrap gap-1">
                                    @foreach ($line['available'] as $mod)
                                        @php $active = collect($line['modifiers'])->contains('id', $mod['id']); @endphp
                                        <button
                                            type="button"
                                            wire:click="toggleModifier('{{ $line['key'] }}', {{ $mod['id'] }})"
                                            @class([
                                                'rounded-full px-2 py-0.5 text-xs ring-1 transition',
                                                'bg-primary-500 text-white ring-primary-500' => $active,
                                                'bg-gray-50 text-gray-600 ring-gray-200 hover:bg-gray-100 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10' => ! $active,
                                            ])
                                        >
                                            {{ $mod['name'] }}@if ($mod['price'] > 0) (+{{ number_format($mod['price'], 2) }})@endif
                                        </button>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Note --}}
                            <input
                                type="text"
                                wire:model.lazy="cart.{{ $loop->index }}.note"
                                placeholder="{{ __('system.note') }}"
                                class="fi-input mt-2 block w-full rounded-lg border-gray-200 text-xs shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white"
                            />

                            {{-- Quantity + line total --}}
                            <div class="mt-2 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <button type="button" wire:click="decrementQuantity('{{ $line['key'] }}')" class="flex h-6 w-6 items-center justify-center rounded-full bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/10 dark:text-white">−</button>
                                    <span class="w-6 text-center text-sm font-medium">{{ $line['quantity'] }}</span>
                                    <button type="button" wire:click="incrementQuantity('{{ $line['key'] }}')" class="flex h-6 w-6 items-center justify-center rounded-full bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/10 dark:text-white">+</button>
                                </div>
                                <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ number_format($this->lineTotal($line), 2) }} EGP</span>
                            </div>
                        </div>
                    @empty
                        <p class="py-8 text-center text-sm text-gray-400">{{ __('system.drag_products_here') }}</p>
                    @endforelse
                </div>

                {{-- Totals --}}
                <div class="mt-4 space-y-1 border-t border-gray-100 pt-3 text-sm dark:border-white/10">
                    <div class="flex justify-between text-gray-600 dark:text-gray-400">
                        <span>{{ __('system.subtotal') }}</span>
                        <span>{{ number_format($this->subtotal, 2) }} EGP</span>
                    </div>
                    <div class="flex justify-between text-gray-600 dark:text-gray-400">
                        <span>{{ __('system.tax') }} (15%)</span>
                        <span>{{ number_format($this->tax, 2) }} EGP</span>
                    </div>
                    <div class="flex justify-between text-base font-semibold text-gray-950 dark:text-white">
                        <span>{{ __('system.total') }}</span>
                        <span>{{ number_format($this->total, 2) }} EGP</span>
                    </div>
                </div>

                <div class="mt-4 flex gap-2">
                    <x-filament::button color="gray" wire:click="clearCart" class="flex-1" :disabled="count($cart) === 0">
                        {{ __('system.clear') }}
                    </x-filament::button>
                    <x-filament::button wire:click="saveOrder" class="flex-1" :disabled="count($cart) === 0">
                        {{ __('system.save_order') }}
                    </x-filament::button>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
