<x-filament-panels::page>
    {{ $this->form }}

    <div class="grid gap-4 sm:grid-cols-3">
        @foreach (['payable' => 'Payable', 'paid' => 'Paid', 'balance' => 'Balance due'] as $key => $label)
            <x-filament::section>
                <p class="text-sm text-gray-500">{{ $label }}</p>
                <p class="text-2xl font-semibold">Rs {{ number_format($statement[$key], 2) }}</p>
            </x-filament::section>
        @endforeach
    </div>

    <x-filament::section>
        <x-slot name="heading">Delivered units</x-slot>

        @if (empty($statement['lines']))
            <p class="text-sm text-gray-500">No delivered sales in this period.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-white/10">
                            <th class="py-2 pe-4">SKU</th>
                            <th class="py-2 pe-4">Product</th>
                            <th class="py-2 pe-4 text-right">Qty</th>
                            <th class="py-2 pe-4 text-right">Unit supply price</th>
                            <th class="py-2 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($statement['lines'] as $line)
                            <tr class="border-b border-gray-100 last:border-0 dark:border-white/5">
                                <td class="py-2 pe-4 font-mono">{{ $line['sku'] }}</td>
                                <td class="py-2 pe-4">{{ $line['product_name'] }}</td>
                                <td class="py-2 pe-4 text-right">{{ $line['quantity'] }}</td>
                                <td class="py-2 pe-4 text-right">{{ number_format($line['unit_supply_price'], 2) }}</td>
                                <td class="py-2 text-right">{{ number_format($line['amount'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
