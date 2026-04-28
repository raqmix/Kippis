<x-filament-panels::page>

    {{-- Filter form --}}
    <x-filament::section>
        <form wire:submit="applyFilters">
            {{ $this->form }}
            <div class="mt-4">
                <x-filament::button type="submit" icon="heroicon-o-funnel">
                    Apply Filters
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    {{-- Summary cards --}}
    @if (!empty($report))
        @php
            $totals       = $report['totals'] ?? [];
            $discrepancies = $report['discrepancies'] ?? [];
        @endphp

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <x-filament::card>
                <div class="text-sm text-gray-500 dark:text-gray-400">Total Captured</div>
                <div class="text-2xl font-bold text-green-600">
                    {{ number_format(($totals['captures'] ?? 0) / 100, 2) }} EGP
                </div>
                <div class="text-xs text-gray-400">{{ number_format($totals['captures'] ?? 0) }} pt</div>
            </x-filament::card>

            <x-filament::card>
                <div class="text-sm text-gray-500 dark:text-gray-400">Total Refunded</div>
                <div class="text-2xl font-bold text-red-500">
                    {{ number_format(($totals['refunds'] ?? 0) / 100, 2) }} EGP
                </div>
                <div class="text-xs text-gray-400">{{ number_format($totals['refunds'] ?? 0) }} pt</div>
            </x-filament::card>

            <x-filament::card>
                <div class="text-sm text-gray-500 dark:text-gray-400">Total Voided</div>
                <div class="text-2xl font-bold text-orange-500">
                    {{ number_format(($totals['voids'] ?? 0) / 100, 2) }} EGP
                </div>
                <div class="text-xs text-gray-400">{{ number_format($totals['voids'] ?? 0) }} pt</div>
            </x-filament::card>

            <x-filament::card>
                <div class="text-sm text-gray-500 dark:text-gray-400">Net</div>
                <div class="text-2xl font-bold {{ ($totals['net'] ?? 0) >= 0 ? 'text-primary-600' : 'text-red-600' }}">
                    {{ number_format(($totals['net'] ?? 0) / 100, 2) }} EGP
                </div>
                <div class="text-xs text-gray-400">{{ $report['transaction_count'] ?? 0 }} transactions</div>
            </x-filament::card>
        </div>

        {{-- Per-gateway breakdown --}}
        @if (!empty($report['summary']))
            <x-filament::section heading="Breakdown by Gateway" collapsible>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-left text-gray-500">
                                <th class="py-2 pr-4">Gateway</th>
                                <th class="py-2 pr-4">Captures</th>
                                <th class="py-2 pr-4">Capture Total</th>
                                <th class="py-2 pr-4">Refunds</th>
                                <th class="py-2 pr-4">Refund Total</th>
                                <th class="py-2 pr-4">Voids</th>
                                <th class="py-2 pr-4">Net</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($report['summary'] as $row)
                                <tr class="border-b hover:bg-gray-50 dark:hover:bg-white/5">
                                    <td class="py-2 pr-4 font-medium capitalize">{{ $row['gateway'] }}</td>
                                    <td class="py-2 pr-4">{{ $row['capture_count'] }}</td>
                                    <td class="py-2 pr-4 text-green-600">{{ number_format($row['capture_total'] / 100, 2) }} EGP</td>
                                    <td class="py-2 pr-4">{{ $row['refund_count'] }}</td>
                                    <td class="py-2 pr-4 text-red-500">{{ number_format($row['refund_total'] / 100, 2) }} EGP</td>
                                    <td class="py-2 pr-4">{{ $row['void_count'] }}</td>
                                    <td class="py-2 pr-4 font-semibold {{ $row['net'] >= 0 ? 'text-primary-600' : 'text-red-600' }}">
                                        {{ number_format($row['net'] / 100, 2) }} EGP
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

        {{-- Discrepancy alerts --}}
        @if (count($discrepancies) > 0)
            <x-filament::section heading="{{ count($discrepancies) }} Discrepanc{{ count($discrepancies) === 1 ? 'y' : 'ies' }} Found">
                <div class="space-y-2">
                    @foreach ($discrepancies as $issue)
                        <div class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-900 dark:bg-red-950">
                            <x-heroicon-o-exclamation-triangle class="mt-0.5 h-5 w-5 flex-shrink-0 text-red-500" />
                            <div>
                                <span class="text-xs font-semibold uppercase text-red-600">{{ str_replace('_', ' ', $issue['type']) }}</span>
                                <p class="text-sm text-red-700 dark:text-red-300">{{ $issue['message'] }}</p>
                                @if (!empty($issue['ids']))
                                    <p class="text-xs text-red-500">Transaction IDs: {{ implode(', ', $issue['ids']) }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif
    @endif

    {{-- Transaction table --}}
    <x-filament::section heading="Transactions">
        {{ $this->table }}
    </x-filament::section>

</x-filament-panels::page>
