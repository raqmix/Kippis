<x-filament-panels::page>
    <x-filament::section>
        <p class="mb-4 text-sm">{{ __('loyalty_rules.draw.help') }}</p>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div>
                <label for="draw-month" class="text-sm font-medium">{{ __('loyalty_rules.draw.month') }}</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select id="draw-month" wire:model.live="month">
                        @foreach ($this->monthOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            <x-filament::button wire:click="exportCsv" icon="heroicon-o-arrow-down-tray">
                {{ __('loyalty_rules.draw.export') }}
            </x-filament::button>
            <x-filament::button wire:click="pickWinner" color="gray" icon="heroicon-o-sparkles">
                {{ __('loyalty_rules.draw.pick_winner') }}
            </x-filament::button>
        </div>

        <p class="mt-4 text-sm">{{ __('loyalty_rules.draw.entries') }}: <strong>{{ $this->entryCount() }}</strong></p>
    </x-filament::section>

    @if ($winner)
        <x-filament::section>
            <x-slot name="heading">{{ __('loyalty_rules.draw.winner') }}</x-slot>
            <dl class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                <dt class="font-medium">{{ __('loyalty_rules.draw.customer') }}</dt>
                <dd>#{{ $winner['customer_id'] }} {{ $winner['name'] }} {{ $winner['phone'] }}</dd>
                <dt class="font-medium">{{ __('loyalty_rules.draw.order') }}</dt>
                <dd>#{{ $winner['order_id'] }} - {{ $winner['date'] }}</dd>
                <dt class="font-medium">{{ __('loyalty_rules.draw.store') }}</dt>
                <dd>{{ $winner['store'] ?? '-' }}</dd>
            </dl>
        </x-filament::section>
    @endif
</x-filament-panels::page>
