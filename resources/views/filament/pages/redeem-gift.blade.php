<x-filament-panels::page>
    <form wire:submit="lookup" class="flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="flex-1">
            <label for="redeem-code" class="text-sm font-medium">{{ __('rewards.admin.code') }}</label>
            <x-filament::input.wrapper>
                <x-filament::input id="redeem-code" type="text" wire:model="code" autofocus autocomplete="off"
                    placeholder="{{ __('rewards.admin.code_placeholder') }}" />
            </x-filament::input.wrapper>
        </div>
        <x-filament::button type="submit" icon="heroicon-o-magnifying-glass">
            {{ __('rewards.admin.find') }}
        </x-filament::button>
    </form>

    @php($entry = $this->entry())

    @if ($searched && ! $entry)
        <x-filament::section>
            <p class="text-danger-600">{{ __('rewards.not_found') }}</p>
        </x-filament::section>
    @elseif ($entry)
        @php($reason = $this->blockingReason())
        <x-filament::section>
            <x-slot name="heading">{{ $entry->getTitle(app()->getLocale()) }}</x-slot>
            <dl class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                <dt class="font-medium">{{ __('rewards.admin.customer') }}</dt>
                <dd>{{ $entry->customer?->name ?? '-' }} {{ $entry->customer?->phone ? '(' . $entry->customer->phone . ')' : '' }}</dd>
                <dt class="font-medium">{{ __('rewards.admin.code') }}</dt>
                <dd class="font-mono">{{ $entry->code }}</dd>
                <dt class="font-medium">{{ __('rewards.admin.type') }}</dt>
                <dd>{{ $entry->reward_type }} / {{ $entry->source }}</dd>
                <dt class="font-medium">{{ __('rewards.admin.expires_at') }}</dt>
                <dd>{{ $entry->expires_at?->timezone(config('core.business_timezone'))->format('Y-m-d H:i') ?? '-' }}</dd>
                <dt class="font-medium">{{ __('rewards.admin.status') }}</dt>
                <dd>{{ $entry->displayStatus() }}</dd>
                @if ($entry->used_at)
                    <dt class="font-medium">{{ __('rewards.admin.used_at') }}</dt>
                    <dd>
                        {{ $entry->used_at->timezone(config('core.business_timezone'))->format('Y-m-d H:i') }}
                        @if ($entry->usedByAdmin) ({{ $entry->usedByAdmin->name }}) @endif
                        @if ($entry->used_order_id) #{{ $entry->used_order_id }} @endif
                    </dd>
                @endif
            </dl>

            <div class="mt-4">
                @if ($reason)
                    <p class="text-danger-600">{{ __('rewards.' . $reason) }}</p>
                @else
                    <x-filament::button color="success" icon="heroicon-o-check" wire:click="markUsed"
                        wire:confirm="{{ __('rewards.admin.confirm') }}">
                        {{ __('rewards.admin.mark_used') }}
                    </x-filament::button>
                @endif
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
