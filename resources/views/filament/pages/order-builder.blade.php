<x-filament-panels::page>
    <style>
        .ob-wrap { display: grid; grid-template-columns: 1fr; gap: 1.5rem; }
        @media (min-width: 1024px) { .ob-wrap { grid-template-columns: 2fr 1fr; } }

        .ob-card-panel {
            background: #fff; border-radius: 0.75rem; padding: 1rem;
            box-shadow: 0 1px 2px rgba(0,0,0,.05); border: 1px solid rgba(0,0,0,.05);
        }
        .dark .ob-card-panel { background: #18181b; border-color: rgba(255,255,255,.1); }

        .ob-head { display: flex; align-items: center; justify-content: space-between; gap: .75rem; margin-bottom: 1rem; }
        .ob-title { font-size: 1rem; font-weight: 600; color: #111827; }
        .dark .ob-title { color: #fff; }

        .ob-search {
            width: 14rem; max-width: 50%; border: 1px solid #d1d5db; border-radius: .5rem;
            padding: .4rem .6rem; font-size: .875rem; background: #fff; color: #111827;
        }
        .dark .ob-search { background: rgba(255,255,255,.05); border-color: rgba(255,255,255,.1); color: #fff; }

        /* Product grid */
        .ob-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: .75rem; }
        @media (min-width: 640px) { .ob-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (min-width: 1280px) { .ob-grid { grid-template-columns: repeat(4, 1fr); } }

        .ob-product {
            cursor: grab; user-select: none; border: 1px solid #e5e7eb; border-radius: .75rem;
            padding: .6rem; background: #fff; transition: border-color .15s, box-shadow .15s;
        }
        .ob-product:hover { border-color: #fbbf24; box-shadow: 0 4px 12px rgba(0,0,0,.08); }
        .ob-product:active { cursor: grabbing; }
        .dark .ob-product { background: rgba(255,255,255,.05); border-color: rgba(255,255,255,.1); }

        .ob-thumb {
            width: 100%; aspect-ratio: 1 / 1; border-radius: .5rem; overflow: hidden;
            background: #f3f4f6; margin-bottom: .5rem; display: flex; align-items: center; justify-content: center;
        }
        .dark .ob-thumb { background: rgba(255,255,255,.08); }
        .ob-thumb img { width: 100%; height: 100%; object-fit: cover; }

        .ob-name { font-size: .85rem; font-weight: 500; color: #111827; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .dark .ob-name { color: #fff; }
        .ob-price { font-size: .75rem; color: #d97706; }

        /* Cart */
        .ob-cart { position: sticky; top: 1rem; transition: box-shadow .15s; }
        .ob-cart.is-over { box-shadow: 0 0 0 2px #f59e0b; }

        .ob-field-label { display: block; font-size: .72rem; font-weight: 500; color: #4b5563; margin-bottom: .25rem; }
        .dark .ob-field-label { color: #9ca3af; }
        .ob-select {
            width: 100%; border: 1px solid #d1d5db; border-radius: .5rem; padding: .4rem .5rem;
            font-size: .85rem; background: #fff; color: #111827;
        }
        .dark .ob-select { background: rgba(255,255,255,.05); border-color: rgba(255,255,255,.1); color: #fff; }

        .ob-divider { border-top: 1px solid #f3f4f6; margin-top: .75rem; padding-top: .75rem; }
        .dark .ob-divider { border-color: rgba(255,255,255,.1); }

        .ob-lines { min-height: 110px; display: flex; flex-direction: column; gap: .6rem; }
        .ob-line { border: 1px solid #e5e7eb; border-radius: .5rem; padding: .6rem; }
        .dark .ob-line { border-color: rgba(255,255,255,.1); }
        .ob-line-top { display: flex; align-items: flex-start; justify-content: space-between; gap: .5rem; }
        .ob-line-name { font-size: .85rem; font-weight: 500; color: #111827; }
        .dark .ob-line-name { color: #fff; }
        .ob-line-unit { font-size: .72rem; color: #6b7280; }

        .ob-x { background: none; border: none; cursor: pointer; color: #9ca3af; font-size: 1rem; line-height: 1; }
        .ob-x:hover { color: #ef4444; }

        .ob-mods { display: flex; flex-wrap: wrap; gap: .25rem; margin-top: .5rem; }
        .ob-chip {
            border: 1px solid #e5e7eb; background: #f9fafb; color: #4b5563; cursor: pointer;
            border-radius: 999px; padding: .12rem .55rem; font-size: .72rem; transition: all .12s;
        }
        .ob-chip:hover { background: #f3f4f6; }
        .ob-chip.is-on { background: #f59e0b; border-color: #f59e0b; color: #fff; }
        .dark .ob-chip { background: rgba(255,255,255,.05); border-color: rgba(255,255,255,.1); color: #d1d5db; }

        .ob-note {
            width: 100%; margin-top: .5rem; border: 1px solid #e5e7eb; border-radius: .5rem;
            padding: .3rem .5rem; font-size: .75rem; background: #fff; color: #111827;
        }
        .dark .ob-note { background: rgba(255,255,255,.05); border-color: rgba(255,255,255,.1); color: #fff; }

        .ob-qty { display: flex; align-items: center; justify-content: space-between; margin-top: .5rem; }
        .ob-qty-controls { display: flex; align-items: center; gap: .5rem; }
        .ob-step {
            width: 1.5rem; height: 1.5rem; border-radius: 999px; border: none; cursor: pointer;
            background: #f3f4f6; color: #374151; font-size: 1rem; line-height: 1; display: flex;
            align-items: center; justify-content: center;
        }
        .ob-step:hover { background: #e5e7eb; }
        .dark .ob-step { background: rgba(255,255,255,.1); color: #fff; }
        .ob-qty-num { width: 1.5rem; text-align: center; font-size: .85rem; font-weight: 500; }
        .ob-line-total { font-size: .85rem; font-weight: 600; color: #111827; }
        .dark .ob-line-total { color: #fff; }

        .ob-empty { text-align: center; color: #9ca3af; font-size: .85rem; padding: 2rem 0; }

        .ob-totals { margin-top: 1rem; }
        .ob-total-row { display: flex; justify-content: space-between; font-size: .85rem; color: #4b5563; margin-bottom: .2rem; }
        .dark .ob-total-row { color: #9ca3af; }
        .ob-total-row.grand { font-size: 1rem; font-weight: 600; color: #111827; }
        .dark .ob-total-row.grand { color: #fff; }

        .ob-actions { display: flex; gap: .5rem; margin-top: 1rem; }
        .ob-btn {
            flex: 1; border-radius: .5rem; padding: .5rem .75rem; font-size: .85rem; font-weight: 500;
            cursor: pointer; border: 1px solid transparent; text-align: center;
        }
        .ob-btn[disabled] { opacity: .5; cursor: not-allowed; }
        .ob-btn-gray { background: #f3f4f6; color: #374151; }
        .ob-btn-gray:hover:not([disabled]) { background: #e5e7eb; }
        .dark .ob-btn-gray { background: rgba(255,255,255,.1); color: #fff; }
        .ob-btn-primary { background: #f59e0b; color: #fff; }
        .ob-btn-primary:hover:not([disabled]) { background: #d97706; }
    </style>

    <div class="ob-wrap">

        {{-- ============ LEFT: Product picker ============ --}}
        <div class="ob-card-panel">
            <div class="ob-head">
                <span class="ob-title">{{ __('system.products') }}</span>
                <input type="text" wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('system.search') }}..." class="ob-search" />
            </div>

            <div class="ob-grid">
                @forelse ($this->products as $product)
                    <div
                        draggable="true"
                        x-on:dragstart="$event.dataTransfer.setData('text/plain', '{{ $product['id'] }}'); $event.dataTransfer.effectAllowed = 'copy';"
                        wire:click="addToCart({{ $product['id'] }})"
                        class="ob-product"
                        title="{{ __('system.drag_or_click_to_add') }}"
                    >
                        <div class="ob-thumb">
                            @if ($product['image'])
                                <img src="{{ $product['image'] }}" alt="{{ $product['name'] }}" />
                            @else
                                <x-heroicon-o-cube style="width:2.5rem;height:2.5rem;color:#d1d5db;" />
                            @endif
                        </div>
                        <p class="ob-name">{{ $product['name'] }}</p>
                        <p class="ob-price">{{ number_format($product['price'], 2) }} EGP</p>
                    </div>
                @empty
                    <p class="ob-empty" style="grid-column: 1 / -1;">{{ __('system.no_results') }}</p>
                @endforelse
            </div>
        </div>

        {{-- ============ RIGHT: Cart / order ============ --}}
        <div>
            <div
                x-data="{ over: false }"
                x-on:dragover.prevent="over = true"
                x-on:dragleave.prevent="over = false"
                x-on:drop.prevent="over = false; $wire.addToCart(parseInt($event.dataTransfer.getData('text/plain')))"
                :class="{ 'is-over': over }"
                class="ob-card-panel ob-cart"
            >
                <span class="ob-title">{{ __('system.create_order') }}</span>

                {{-- Order meta --}}
                <div style="margin-top: 1rem; display:flex; flex-direction:column; gap:.6rem;">
                    <div>
                        <label class="ob-field-label">{{ __('system.store') }} *</label>
                        <select wire:model="storeId" class="ob-select">
                            <option value="">—</option>
                            @foreach ($this->storeOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="ob-field-label">{{ __('system.channel') }}</label>
                        <select wire:model="channelId" class="ob-select">
                            <option value="">—</option>
                            @foreach ($this->channelOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="ob-field-label">{{ __('system.customer') }}</label>
                        <select wire:model="customerId" class="ob-select">
                            <option value="">{{ __('system.guest') }}</option>
                            @foreach ($this->customerOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Cart lines --}}
                <div class="ob-lines ob-divider">
                    @forelse ($cart as $line)
                        <div wire:key="{{ $line['key'] }}" class="ob-line">
                            <div class="ob-line-top">
                                <div style="min-width:0;">
                                    <p class="ob-line-name">{{ $line['name'] }}</p>
                                    <p class="ob-line-unit">{{ number_format($line['unit_price'], 2) }} EGP</p>
                                </div>
                                <button type="button" class="ob-x" wire:click="removeLine('{{ $line['key'] }}')">&times;</button>
                            </div>

                            @if (! empty($line['available']))
                                <div class="ob-mods">
                                    @foreach ($line['available'] as $mod)
                                        @php $active = collect($line['modifiers'])->contains('id', $mod['id']); @endphp
                                        <button type="button" wire:click="toggleModifier('{{ $line['key'] }}', {{ $mod['id'] }})"
                                            class="ob-chip {{ $active ? 'is-on' : '' }}">
                                            {{ $mod['name'] }}@if ($mod['price'] > 0) (+{{ number_format($mod['price'], 2) }})@endif
                                        </button>
                                    @endforeach
                                </div>
                            @endif

                            <input type="text" wire:model.blur="cart.{{ $loop->index }}.note"
                                placeholder="{{ __('system.note') }}" class="ob-note" />

                            <div class="ob-qty">
                                <div class="ob-qty-controls">
                                    <button type="button" class="ob-step" wire:click="decrementQuantity('{{ $line['key'] }}')">&minus;</button>
                                    <span class="ob-qty-num">{{ $line['quantity'] }}</span>
                                    <button type="button" class="ob-step" wire:click="incrementQuantity('{{ $line['key'] }}')">+</button>
                                </div>
                                <span class="ob-line-total">{{ number_format($this->lineTotal($line), 2) }} EGP</span>
                            </div>
                        </div>
                    @empty
                        <p class="ob-empty">{{ __('system.drag_products_here') }}</p>
                    @endforelse
                </div>

                {{-- Totals --}}
                <div class="ob-totals ob-divider">
                    <div class="ob-total-row">
                        <span>{{ __('system.subtotal') }}</span>
                        <span>{{ number_format($this->subtotal, 2) }} EGP</span>
                    </div>
                    <div class="ob-total-row">
                        <span>{{ __('system.tax') }} (15%)</span>
                        <span>{{ number_format($this->tax, 2) }} EGP</span>
                    </div>
                    <div class="ob-total-row grand">
                        <span>{{ __('system.total') }}</span>
                        <span>{{ number_format($this->total, 2) }} EGP</span>
                    </div>
                </div>

                <div class="ob-actions">
                    <button type="button" class="ob-btn ob-btn-gray" wire:click="clearCart" @disabled(count($cart) === 0)>
                        {{ __('system.clear') }}
                    </button>
                    <button type="button" class="ob-btn ob-btn-primary" wire:click="saveOrder" @disabled(count($cart) === 0)>
                        {{ __('system.save_order') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
