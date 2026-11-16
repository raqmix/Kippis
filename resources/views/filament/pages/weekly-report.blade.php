<x-filament-panels::page>
    @php($report = $this->report())

    <x-filament::section>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div>
                <label for="weekly-report-week" class="text-sm font-medium">Week containing</label>
                <x-filament::input.wrapper>
                    <x-filament::input id="weekly-report-week" type="date" wire:model.live="week" />
                </x-filament::input.wrapper>
            </div>
            <x-filament::button color="gray" wire:click="previousWeek" icon="heroicon-o-chevron-left">Previous</x-filament::button>
            <x-filament::button color="gray" wire:click="nextWeek" icon-position="after" icon="heroicon-o-chevron-right">Next</x-filament::button>
            <x-filament::button wire:click="exportCsv" icon="heroicon-o-arrow-down-tray">Export CSV</x-filament::button>
        </div>
        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
            Monday {{ $report['week_start'] }} to Sunday {{ $report['week_end'] }} (Cairo time). Orders and revenue count completed orders only.
        </p>
    </x-filament::section>

    <x-filament::section>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left">
                        <th class="py-2 pr-4">Store</th>
                        <th class="py-2 pr-4 text-right">New sign-ups</th>
                        <th class="py-2 pr-4 text-right">First orders</th>
                        <th class="py-2 pr-4 text-right">App orders</th>
                        <th class="py-2 pr-4 text-right">App revenue (EGP)</th>
                        <th class="py-2 pr-4 text-right">All orders</th>
                        <th class="py-2 text-right">All revenue (EGP)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report['rows'] as $row)
                        <tr class="border-b">
                            <td class="py-2 pr-4">{{ $row['label'] }}</td>
                            <td class="py-2 pr-4 text-right">{{ number_format($row['signups']) }}</td>
                            <td class="py-2 pr-4 text-right">{{ number_format($row['first_orders']) }}</td>
                            <td class="py-2 pr-4 text-right">{{ number_format($row['app_orders']) }}</td>
                            <td class="py-2 pr-4 text-right">{{ number_format($row['app_revenue'], 2) }}</td>
                            <td class="py-2 pr-4 text-right">{{ number_format($row['all_orders']) }}</td>
                            <td class="py-2 text-right">{{ number_format($row['all_revenue'], 2) }}</td>
                        </tr>
                    @endforeach
                    @php($t = $report['totals'])
                    <tr class="font-semibold">
                        <td class="py-2 pr-4">Total</td>
                        <td class="py-2 pr-4 text-right">{{ number_format($t['signups']) }}</td>
                        <td class="py-2 pr-4 text-right">{{ number_format($t['first_orders']) }}</td>
                        <td class="py-2 pr-4 text-right">{{ number_format($t['app_orders']) }}</td>
                        <td class="py-2 pr-4 text-right">{{ number_format($t['app_revenue'], 2) }}</td>
                        <td class="py-2 pr-4 text-right">{{ number_format($t['all_orders']) }}</td>
                        <td class="py-2 text-right">{{ number_format($t['all_revenue'], 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
