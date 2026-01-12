<div class="space-y-4">
    @forelse($items as $index => $item)
        <div class="border rounded-lg p-4 bg-gray-50 dark:bg-gray-800">
            <div class="flex justify-between items-start mb-2">
                <div class="flex-1">
                    <h4 class="font-semibold text-lg mb-2">{{ $item['product_name'] ?? 'Product #' . ($index + 1) }}</h4>
                    
                    @if(isset($item['modifiers']) && is_array($item['modifiers']) && count($item['modifiers']) > 0)
                        <div class="mt-2 ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('system.modifiers') }}:</p>
                            <ul class="list-disc list-inside space-y-1">
                                @foreach($item['modifiers'] as $modifier)
                                    @if(is_array($modifier) && isset($modifier['name']))
                                        <li class="text-sm text-gray-700 dark:text-gray-300">
                                            {{ $modifier['name'] }}
                                            @if(isset($modifier['price']) && $modifier['price'] > 0)
                                                (+{{ number_format($modifier['price'], 2) }} EGP)
                                            @endif
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        {{ __('system.quantity') }}: <strong>{{ $item['quantity'] ?? 1 }}</strong>
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        {{ __('system.unit_price') }}: <strong>{{ number_format($item['price'] ?? 0, 2) }} EGP</strong>
                    </p>
                    @php
                        $itemTotal = ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
                    @endphp
                    <p class="text-lg font-bold text-primary-600 dark:text-primary-400 mt-1">
                        {{ __('system.total') }}: {{ number_format($itemTotal, 2) }} EGP
                    </p>
                </div>
            </div>
        </div>
    @empty
        <p class="text-gray-500 dark:text-gray-400">{{ __('system.no_items') }}</p>
    @endforelse
</div>

