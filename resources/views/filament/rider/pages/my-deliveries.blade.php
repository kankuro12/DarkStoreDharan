<x-filament-panels::page>
    <style>
        .rider-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); gap: 0.75rem; margin-bottom: 1rem; }
        .rider-stat { border: 1px solid rgba(120,113,108,.22); border-radius: .6rem; padding: .7rem .9rem; }
        .rider-stat .label { font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; color: #6b7280; }
        .dark .rider-stat .label { color: #9ca3af; }
        .rider-stat .value { font-size: 1.3rem; font-weight: 700; margin-top: .15rem; }
        .rider-card { border: 1px solid rgba(120,113,108,.25); border-radius: .7rem; padding: 1rem 1.1rem; margin-bottom: .9rem; }
        .rider-card .head { display: flex; flex-wrap: wrap; gap: .6rem; align-items: center; justify-content: space-between; }
        .rider-card .order-no { font-family: monospace; font-weight: 700; font-size: 1rem; }
        .rider-card .badge { font-size: .72rem; font-weight: 600; border-radius: 9999px; padding: .15rem .6rem; border: 1px solid rgba(120,113,108,.35); }
        .rider-card .grid2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr)); gap: .8rem; margin-top: .7rem; }
        .rider-card .label { font-size: .72rem; font-weight: 600; text-transform: uppercase; color: #6b7280; }
        .dark .rider-card .label { color: #9ca3af; }
        .rider-card ul { margin: .25rem 0 0; padding-left: 1.1rem; list-style: disc; font-size: .85rem; }
        .rider-card .cash { color: #059669; font-weight: 700; }
        .rider-card .actions { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .85rem; }
        .rider-card .actions button { border-radius: .5rem; padding: .45rem .9rem; font-size: .85rem; font-weight: 600; cursor: pointer; border: 1px solid transparent; }
        .rider-card .actions .primary { background: #059669; color: #fff; }
        .rider-card .actions .secondary { background: transparent; border-color: rgba(120,113,108,.45); color: inherit; }
        .rider-card .actions .danger { background: transparent; border-color: #dc2626; color: #dc2626; }
        .rider-empty { text-align: center; color: #9ca3af; padding: 2.5rem 1rem; border: 1px dashed rgba(120,113,108,.35); border-radius: .7rem; }
        .rider-logout { font-size: .8rem; font-weight: 600; padding: .4rem .9rem; border-radius: .5rem; border: 1px solid rgba(220,38,38,.5); color: #dc2626; background: transparent; cursor: pointer; }
        .rider-logout:hover { background: rgba(220,38,38,.08); }
    </style>

    <div style="display:flex; justify-content:flex-end; margin-bottom:.6rem;">
        <form method="POST" action="{{ route('filament.rider.auth.logout') }}">
            @csrf
            <button type="submit" class="rider-logout">Sign out</button>
        </form>
    </div>

    <div class="rider-stats" wire:poll.30s>
        <div class="rider-stat">
            <div class="label">Open Deliveries</div>
            <div class="value">{{ $this->stats['open'] }}</div>
        </div>
        <div class="rider-stat">
            <div class="label">Delivered Today</div>
            <div class="value">{{ $this->stats['deliveredToday'] }}</div>
        </div>
        <div class="rider-stat">
            <div class="label">Cash to Collect</div>
            <div class="value">Rs {{ number_format($this->stats['cashToCollect']) }}</div>
        </div>
    </div>

    @forelse ($this->orders as $order)
        <div class="rider-card" wire:key="order-{{ $order->id }}">
            <div class="head">
                <span class="order-no">#{{ $order->order_number }}</span>
                <span class="badge">{{ $order->delivery_status->label() }}</span>
                <span class="badge">{{ $order->order_status->label() }}</span>
            </div>

            <div class="grid2">
                <div>
                    <div class="label">Deliver to</div>
                    <div>{{ $order->address?->full_name ?? '—' }}</div>
                    <div>{{ $order->address?->formatted_address ?? '—' }}</div>
                    <div>{{ $order->address?->phone ?? '' }}</div>
                </div>
                <div>
                    <div class="label">Items ({{ $order->items->sum('quantity') }})</div>
                    <ul>
                        @foreach ($order->items as $item)
                            <li>{{ $item->quantity }} x {{ $item->product_name }}</li>
                        @endforeach
                    </ul>
                </div>
                <div>
                    <div class="label">Payment</div>
                    <div>{{ $order->payment_method->label() }}</div>
                    @if ($order->payment_method->value === 'cod' && $order->payment_status->value !== 'paid')
                        <div>Collect: <span class="cash">Rs {{ number_format((float) $order->grand_total) }}</span></div>
                    @else
                        <div>Already paid</div>
                    @endif
                    <div>{{ $order->city?->name }}</div>
                </div>
            </div>

            <div class="actions">
                @if (in_array($order->delivery_status->value, ['pending', 'assigned'], true))
                    <button class="primary" wire:click="markPickedUp({{ $order->id }})">Picked Up</button>
                @elseif ($order->delivery_status->value === 'picked_up')
                    <button class="primary" wire:click="markOutForDelivery({{ $order->id }})">Out for Delivery</button>
                @elseif ($order->delivery_status->value === 'out_for_delivery')
                    <button class="primary" wire:confirm="Confirm the customer received this order?" wire:click="markDelivered({{ $order->id }})">Delivered</button>
                    <button class="danger" wire:confirm="Mark this delivery attempt as failed?" wire:click="markFailed({{ $order->id }})">Failed Attempt</button>
                @elseif ($order->delivery_status->value === 'failed')
                    <button class="secondary" wire:click="markOutForDelivery({{ $order->id }})">Retry Delivery</button>
                @endif
            </div>
        </div>
    @empty
        <div class="rider-empty">
            No open deliveries right now. New assignments from the dark store appear here automatically.
        </div>
    @endforelse
</x-filament-panels::page>
