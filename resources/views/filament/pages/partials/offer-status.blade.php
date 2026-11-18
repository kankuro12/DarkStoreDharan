{{-- Rider offer state for an order on the warehouse board. Expects $order (and optionally $riders for manual assignment). --}}
@if ($order->delivery_agent_id)
    <p style="font-size: 0.7rem; color: #047857; font-weight: 700;">
        🏍 {{ $order->deliveryAgent?->name ?? 'Rider' }} @if ($order->rider_pay !== null) • Rs {{ number_format((float) $order->rider_pay) }} @endif
    </p>
@elseif ($order->offer_opened_at)
    <p style="font-size: 0.7rem; color: #475569;">
        Offer Rs {{ number_format($order->currentOfferPay()) }} · waiting {{ (int) $order->offer_opened_at->diffInMinutes(now()) }} min
    </p>
    @if ($order->offerStuck())
        <p style="font-size: 0.7rem; font-weight: 800; color: #b91c1c; background: #fee2e2; border-radius: 0.4rem; padding: 0.2rem 0.45rem;">
            No rider at max pay — assign manually
        </p>
    @endif
@endif
@if (! $order->delivery_agent_id && isset($riders) && $riders->isNotEmpty())
    <select wire:model="manualRider.{{ $order->id }}" aria-label="Assign rider for order {{ $order->order_number }}" style="font-size: 0.72rem; border: 1px solid #cbd5e1; border-radius: 0.4rem; padding: 0.25rem;">
        <option value="">Auto / offer winner</option>
        @foreach ($riders as $r)
            <option value="{{ $r->id }}">{{ $r->name }}</option>
        @endforeach
    </select>
@endif
