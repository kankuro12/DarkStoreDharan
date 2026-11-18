<x-filament-panels::page>
    <style>
        .offer-toggle { display: flex; align-items: center; justify-content: space-between; gap: .75rem; border: 1px solid rgba(120,113,108,.25); border-radius: .7rem; padding: .8rem 1rem; margin-bottom: 1rem; }
        .offer-toggle .state { font-weight: 700; }
        .offer-toggle .state.on { color: #059669; }
        .offer-toggle button { border-radius: .5rem; padding: .45rem .9rem; font-size: .85rem; font-weight: 600; cursor: pointer; border: 1px solid rgba(120,113,108,.45); background: transparent; color: inherit; }
        .offer-toggle button.go { background: #059669; border-color: #059669; color: #fff; }
        .offer-card { border: 1px solid rgba(120,113,108,.25); border-radius: .7rem; padding: 1rem 1.1rem; margin-bottom: .9rem; }
        .offer-card .head { display: flex; flex-wrap: wrap; gap: .6rem; align-items: baseline; justify-content: space-between; }
        .offer-card .pay { font-size: 1.6rem; font-weight: 800; color: #059669; }
        .offer-card .meta { font-size: .9rem; margin-top: .4rem; display: grid; gap: .15rem; }
        .offer-card .muted { color: #6b7280; }
        .dark .offer-card .muted { color: #9ca3af; }
        .offer-card .actions { display: flex; gap: .5rem; margin-top: .85rem; }
        .offer-card .actions button { border-radius: .5rem; padding: .5rem 1rem; font-size: .9rem; font-weight: 600; cursor: pointer; border: 1px solid transparent; }
        .offer-card .actions .primary { background: #059669; color: #fff; flex: 1; }
        .offer-card .actions .secondary { background: transparent; border-color: rgba(120,113,108,.45); color: inherit; }
        .offer-empty { text-align: center; color: #9ca3af; padding: 2.5rem 1rem; border: 1px dashed rgba(120,113,108,.35); border-radius: .7rem; }
    </style>

    <div wire:poll.10s>
        <div class="offer-toggle">
            @if ($this->isOnline())
                <span class="state on">You are online</span>
                <button type="button" wire:click="toggleOnline">Go offline</button>
            @else
                <span class="state">You are offline</span>
                <button type="button" class="go" wire:click="toggleOnline">Go online</button>
            @endif
        </div>

        @if (! $this->isOnline())
            <div class="offer-empty">Go online to see delivery offers.</div>
        @else
            @forelse ($this->offers as $offer)
                @php($km = \App\Services\Delivery\DeliveryOfferService::distanceKm($offer))
                <div class="offer-card" wire:key="offer-{{ $offer->id }}">
                    <div class="head">
                        <strong>{{ $offer->warehouse?->name }}</strong>
                        <span class="pay">Rs {{ number_format($offer->currentOfferPay()) }}</span>
                    </div>
                    <div class="meta">
                        <span>Drop-off: {{ $offer->address?->area ?? 'Customer area' }}@if ($offer->address?->deliveryZone) ({{ $offer->address->deliveryZone->name }})@endif</span>
                        <span class="muted">
                            {{ $offer->items->sum('quantity') }} items
                            @if ($km !== null) &middot; ~{{ $km }} km @endif
                        </span>
                        @if ($offer->payment_method->value === 'cod' && $offer->payment_status->value !== 'paid')
                            <span>Collect Rs {{ number_format((float) $offer->grand_total) }}</span>
                        @endif
                    </div>
                    <div class="actions">
                        <button type="button" class="primary" wire:click="acceptOffer({{ $offer->id }})">Accept</button>
                        <button type="button" class="secondary" wire:click="declineOffer({{ $offer->id }})">Decline</button>
                    </div>
                </div>
            @empty
                <div class="offer-empty">No offers right now. New orders appear here as soon as packing starts.</div>
            @endforelse
        @endif
    </div>
</x-filament-panels::page>
