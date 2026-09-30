<div>
    <style>
        .ledger-mini { width: 100%; border-collapse: collapse; font-size: .82rem; }
        .ledger-mini th, .ledger-mini td { border-bottom: 1px solid rgba(120,113,108,.22); padding: .4rem .5rem; text-align: left; vertical-align: top; }
        .ledger-mini th { font-weight: 600; color: #6b7280; font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; }
        .ledger-mini .qty-in { color: #059669; font-weight: 600; }
        .ledger-mini .qty-out { color: #dc2626; font-weight: 600; }
        .ledger-mini .muted { color: #9ca3af; }
    </style>

    @if ($movements->isEmpty())
        <p class="muted">No ledger entries yet. The first stock movement will open the ledger with an opening balance.</p>
    @else
        <table class="ledger-mini">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Type</th>
                    <th>Qty</th>
                    <th>Balance</th>
                    <th>Reference</th>
                    <th>Reason</th>
                    <th>By</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($movements as $movement)
                    <tr>
                        <td>{{ $movement->created_at?->format('d M Y H:i') }}</td>
                        <td>{{ $movement->type->label() }}</td>
                        <td class="{{ in_array($movement->type, $inboundTypes, true) ? 'qty-in' : 'qty-out' }}">
                            {{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}
                        </td>
                        <td>{{ $movement->balance_after }}</td>
                        <td>{{ $movement->reference_code ?? '—' }}</td>
                        <td>{{ $movement->reason ?? '—' }}</td>
                        <td>{{ $movement->creator?->name ?? 'System' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="muted" style="margin-top:.5rem;">Showing latest 25 entries. Full history: Stock Ledger page. Entries are immutable.</p>
    @endif
</div>
