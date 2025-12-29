@php
    $locales = $this->availableLocales;
    $currentLocale = $this->locale ?? 'en';
@endphp

<div class="fi-topbar-item">
    <x-filament::dropdown
        placement="bottom-end"
        teleport="true"
    >
        <x-slot name="trigger">
            <x-filament::icon-button
                icon="heroicon-o-globe-alt"
                :label="__('system.language')"
                color="gray"
                size="lg"
            />
        </x-slot>

        <x-filament::dropdown.list>
            @foreach($locales as $code => $name)
                <x-filament::dropdown.list.item
                    wire:click="switchLocale('{{ $code }}')"
                    :active="$currentLocale === $code"
                >
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium">{{ $code === 'ar' ? 'AR' : 'EN' }}</span>
                        @if($currentLocale === $code)
                            <x-filament::icon
                                icon="heroicon-o-check"
                                class="h-4 w-4 text-primary-600 dark:text-primary-400"
                            />
                        @endif
                    </div>
                </x-filament::dropdown.list.item>
            @endforeach
        </x-filament::dropdown.list>
    </x-filament::dropdown>
</div>
