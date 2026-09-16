<x-filament-panels::page>
    @php($counts = $this->getTabCounts())

    <div class="flex flex-wrap items-center gap-2 mb-4">
        @foreach (['live' => 'Live', 'drafts' => 'Drafts', 'hidden' => 'Hidden'] as $key => $label)
            <button
                type="button"
                wire:click="$set('activeTab', '{{ $key }}'); $refresh"
                class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium border transition
                       {{ $activeTab === $key
                           ? 'bg-primary-600 text-white border-primary-600'
                           : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:bg-gray-50' }}"
            >
                {{ $label }}
                <span class="inline-flex items-center justify-center min-w-[1.5rem] h-5 px-1 rounded-full text-xs font-semibold
                             {{ $activeTab === $key
                                 ? 'bg-white/25 text-white'
                                 : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400' }}">
                    {{ $counts[$key] }}
                </span>
            </button>
        @endforeach

        @if (filled($this->record->foodics_menu_group_id))
            <span class="ml-auto text-xs text-gray-500 dark:text-gray-400">
                Foodics group: <code class="text-gray-700 dark:text-gray-200">{{ $this->record->foodics_menu_group_id }}</code>
                @if ($this->record->synced_from_foodics_at)
                    · last synced {{ $this->record->synced_from_foodics_at->diffForHumans() }}
                @endif
            </span>
        @endif
    </div>

    {{ $this->table }}
</x-filament-panels::page>
