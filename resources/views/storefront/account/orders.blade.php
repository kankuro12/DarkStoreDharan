@extends('layouts.storefront', ['title' => 'Order History - ' . ($storeSettings['store_name'] ?? 'DarkStore')])

@section('content')
<div class="max-w-4xl mx-auto py-4">

    <div class="mb-6">
        <h1 class="text-xl sm:text-2xl font-extrabold text-slate-950 tracking-tight">My Account</h1>
        <p class="text-xs text-slate-500">Manage your orders and saved delivery addresses</p>
    </div>

    @include('storefront.partials.account-nav')

    @if($orders->isEmpty())
        <div class="py-16 text-center space-y-3 bg-white rounded-3xl border border-slate-200 shadow-xs">
            <div class="w-14 h-14 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto">
                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" /></svg>
            </div>
            <p class="text-sm font-bold text-slate-800">You haven't placed any orders yet</p>
            <a href="{{ route('storefront.home') }}" class="inline-block mt-2 px-5 py-2.5 bg-slate-950 hover:bg-black text-white font-bold rounded-xl text-xs uppercase tracking-wider transition cursor-pointer shadow-xs">
                Start Shopping
            </a>
        </div>
    @else
        <div class="space-y-4">
            @foreach($orders as $order)
                <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-4 sm:p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-100">
                        <div>
                            <span class="text-xs font-mono font-bold text-slate-900 block">#{{ $order->order_number }}</span>
                            <span class="text-[11px] text-slate-500">
                                Placed {{ $order->placed_at?->format('d M Y, h:i A') }} &bull; {{ $order->city?->name }}
                            </span>
                        </div>
                        <span class="inline-block px-3 py-1 text-[11px] font-black rounded-full uppercase tracking-wider border {{ $order->order_status->badgeClasses() }}">
                            {{ $order->order_status->label() }}
                        </span>
                    </div>

                    <div class="py-3 space-y-1 text-xs text-slate-600">
                        @foreach($order->items->take(3) as $item)
                            <div class="flex justify-between gap-3">
                                <span class="truncate">{{ $item->product_name }} <span class="text-slate-400">&times; {{ $item->quantity }}</span></span>
                                <span class="font-mono text-slate-900 font-semibold shrink-0">Rs {{ number_format($item->total) }}</span>
                            </div>
                        @endforeach
                        @if($order->items->count() > 3)
                            <p class="text-[11px] text-slate-400">+ {{ $order->items->count() - 3 }} more item(s)</p>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-100">
                        <span class="text-sm font-black text-slate-950 font-mono">Rs {{ number_format($order->grand_total) }}</span>
                        <div class="flex items-center gap-2">
                            @if($order->isCancellable())
                                <form action="{{ route('storefront.order.cancel', $order->order_number) }}" method="POST" onsubmit="return confirm('Cancel this order? Any reserved or deducted stock will be released.');">
                                    @csrf
                                    <button type="submit" class="px-3.5 py-2 rounded-xl border border-rose-200 hover:bg-rose-50 text-rose-700 text-xs font-bold transition cursor-pointer">
                                        Cancel
                                    </button>
                                </form>
                            @endif
                            <a href="{{ route('storefront.order.tracking', $order->order_number) }}" class="px-3.5 py-2 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-800 text-xs font-bold transition cursor-pointer">
                                Track Order
                            </a>
                            <form action="{{ route('storefront.account.orders.reorder', $order) }}" method="POST">
                                @csrf
                                <button type="submit" class="px-3.5 py-2 rounded-xl bg-slate-950 hover:bg-black text-white text-xs font-bold transition cursor-pointer">
                                    Reorder
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $orders->links() }}
        </div>
    @endif

</div>
@endsection
