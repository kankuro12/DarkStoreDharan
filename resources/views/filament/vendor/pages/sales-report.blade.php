<x-filament-panels::page>
    {{-- Scoped styles: Filament's stylesheet does not ship arbitrary Tailwind utilities for custom views. --}}
    <style>
        .sales .totals { display: grid; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); gap: 1rem; }
        .sales .total-label { font-size: .8rem; color: #6b7280; }
        .dark .sales .total-label { color: #9ca3af; }
        .sales .total-value { font-size: 1.5rem; font-weight: 700; margin-top: .2rem; }
        .sales .muted { font-size: .9rem; color: #6b7280; }
        .sales .table-wrap { overflow-x: auto; }
        .sales table { width: 100%; border-collapse: collapse; font-size: .875rem; text-align: left; }
        .sales th { font-weight: 600; padding: .5rem 1rem .5rem 0; border-bottom: 1px solid rgba(120,113,108,.3); white-space: nowrap; }
        .sales td { padding: .5rem 1rem .5rem 0; border-bottom: 1px solid rgba(120,113,108,.15); }
        .sales tr:last-child td { border-bottom: 0; }
        .sales .num { text-align: right; white-space: nowrap; }
        .sales .mono { font-family: monospace; }
    </style>

    <div class="sales" style="display: grid; gap: 1.5rem;">
        {{ $this->form }}

        <div class="totals">
            @foreach (['payable' => 'Payable', 'paid' => 'Paid', 'balance' => 'Balance due'] as $key => $label)
                <x-filament::section>
                    <p class="total-label">{{ $label }}</p>
                    <p class="total-value">Rs {{ number_format($statement[$key], 2) }}</p>
                </x-filament::section>
            @endforeach
        </div>

        <x-filament::section>
            <x-slot name="heading">Delivered units</x-slot>

            @if (empty($statement['lines']))
                <p class="muted">No delivered sales in this period.</p>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Product</th>
                                <th class="num">Qty</th>
                                <th class="num">Unit supply price</th>
                                <th class="num">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($statement['lines'] as $line)
                                <tr>
                                    <td class="mono">{{ $line['sku'] }}</td>
                                    <td>{{ $line['product_name'] }}</td>
                                    <td class="num">{{ $line['quantity'] }}</td>
                                    <td class="num">{{ number_format($line['unit_supply_price'], 2) }}</td>
                                    <td class="num">{{ number_format($line['amount'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
