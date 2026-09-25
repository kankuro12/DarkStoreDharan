@extends('layouts.storefront')

@section('content')
<div class="max-w-2xl mx-auto py-6 space-y-6">

    <!-- Top Card: Live Order Status Header with ETA -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-4 text-center">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 text-emerald-900 border border-emerald-200 text-xs font-bold">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <span>LIVE DARK STORE TRACKING</span>
        </div>

        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-950 font-mono tracking-tight">Order #{{ $order->order_number }}</h1>
            <p class="text-xs text-slate-500 mt-1">
                Fulfilling from <strong class="text-slate-800">{{ $order->warehouse?->name ?? 'Local Dark Store' }}</strong>
                • Placed {{ $order->placed_at?->diffForHumans() }}
            </p>
        </div>

        <!-- Live Delivery ETA Banner -->
        @if($order->order_status !== \App\Enums\OrderStatus::Delivered && $order->order_status !== \App\Enums\OrderStatus::Cancelled)
            <div class="bg-amber-50/80 border border-amber-200/80 p-3.5 rounded-2xl max-w-sm mx-auto flex items-center justify-center gap-2.5 text-xs text-amber-950 font-bold">
                <svg class="w-4 h-4 text-amber-600 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                <span>Estimated Arrival: <strong>~{{ $order->city?->estimated_delivery_minutes ?? 25 }} Minutes</strong></span>
            </div>
        @endif

        <div class="pt-1 flex items-center justify-center gap-3">
            <span class="inline-block px-3.5 py-1 text-xs font-black rounded-full uppercase tracking-wider border {{ $order->order_status->badgeClasses() }}">
                {{ $order->order_status->label() }}
            </span>
            <span class="inline-flex items-center gap-1.5 bg-slate-900 text-white px-3.5 py-1 rounded-full text-xs font-mono">
                <span class="text-slate-400 text-[10px]">HANDOFF PIN:</span>
                <span class="text-amber-400 font-black tracking-wider">{{ substr(abs(crc32($order->order_number)), 0, 4) }}</span>
            </span>
        </div>

        @if($canCancel)
            <form action="{{ route('storefront.order.cancel', $order->order_number) }}" method="POST" onsubmit="return confirm('Cancel this order? Any reserved or deducted stock will be released.');">
                @csrf
                <button type="submit" class="text-[11px] font-bold text-rose-600 hover:text-rose-700 underline underline-offset-2 cursor-pointer">
                    Cancel this order
                </button>
            </form>
        @endif
    </div>

    <!-- Stepper Progress -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-6">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h2 class="text-sm font-extrabold text-slate-950">
                Delivery Timeline & SLA Tracking
            </h2>
            <span class="text-xs font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 rounded-full">
                ~{{ $order->city?->estimated_delivery_minutes ?? 30 }}m SLA
            </span>
        </div>

        <div class="space-y-6 relative before:absolute before:left-3.5 before:top-3 before:bottom-3 before:w-0.5 before:bg-slate-200">
            @foreach($steps as $index => $step)
                <div class="relative flex items-start gap-4">
                    <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-black z-10 shrink-0 {{ $step['completed'] ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 text-slate-400 border border-slate-300' }}">
                        @if($step['completed'])
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                        @else
                            {{ $index + 1 }}
                        @endif
                    </span>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs sm:text-sm font-bold {{ $step['completed'] ? 'text-slate-900' : 'text-slate-400' }}">
                                {{ $step['title'] }}
                            </h3>
                            @if($step['time'])
                                <span class="text-[10px] font-semibold text-slate-400 font-mono bg-slate-50 px-2 py-0.5 rounded border border-slate-100">{{ $step['time'] }}</span>
                            @endif
                        </div>
                        <p class="text-[11px] text-slate-500 mt-0.5">{{ $step['description'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Rider Assignment Card -->
        @if($order->deliveryAgent)
            <div class="bg-emerald-50/70 p-4 rounded-2xl border border-emerald-200/80 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold shrink-0 shadow-xs">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                    </div>
                    <div>
                        <span class="text-xs font-extrabold text-slate-950 block">Assigned Rider: {{ $order->deliveryAgent->name }}</span>
                        <span class="text-[11px] text-slate-600">Vehicle: {{ $order->deliveryAgent->vehicle_type ?? 'Motorcycle' }} • {{ $order->deliveryAgent->phone }}</span>
                    </div>
                </div>
                <a href="tel:{{ $order->deliveryAgent->phone }}" class="px-3.5 py-2 bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center gap-1.5 shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                    <span>Call Rider</span>
                </a>
            </div>
        @endif
    </div>

    <!-- Order Items & Receipt Details -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-4">
        <h2 class="text-sm font-extrabold text-slate-950 border-b border-slate-100 pb-3 flex items-center justify-between">
            <span>Items in Order ({{ $order->items->count() }})</span>
            <span class="text-xs font-mono text-slate-500">Order Locked</span>
        </h2>

        <div class="divide-y divide-slate-100 space-y-2 text-xs">
            @foreach($order->items as $item)
                <div class="pt-2 flex items-center justify-between gap-3">
                    <div>
                        <span class="font-bold text-slate-900 block">{{ $item->product_name }}</span>
                        <span class="text-slate-500 text-[11px] font-mono">SKU: {{ $item->sku }} • Qty: {{ $item->quantity }} × Rs {{ number_format($item->unit_price) }}</span>
                    </div>
                    <span class="font-extrabold text-slate-950 font-mono whitespace-nowrap">Rs {{ number_format($item->total) }}</span>
                </div>
            @endforeach
        </div>

        <div class="pt-3 border-t border-slate-100 space-y-2 text-xs text-slate-600">
            <div class="flex justify-between">
                <span>Payment Method</span>
                <span class="font-bold text-slate-900 uppercase font-mono">{{ $order->payment_method->label() }}</span>
            </div>
            <div class="flex justify-between">
                <span>Payment Status</span>
                <span class="font-bold text-slate-900 uppercase font-mono">{{ $order->payment_status->label() }}</span>
            </div>
            <div class="flex justify-between text-base font-black text-slate-950 pt-2 border-t border-slate-200">
                <span>Grand Total</span>
                <span class="font-mono">Rs {{ number_format($order->grand_total) }}</span>
            </div>
        </div>

        <div class="pt-3 border-t border-slate-100 text-xs">
            <strong class="block text-slate-900 font-bold mb-1">Delivering to:</strong>
            <p class="text-slate-600 leading-relaxed">
                {{ $order->address?->full_name }} ({{ $order->address?->phone }})<br>
                {{ $order->address?->formatted_address }}
            </p>
        </div>

        <!-- Returns & Refunds Section -->
        <div class="pt-4 border-t border-slate-100">
            @if($returnRequest)
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 text-xs space-y-2">
                    <div class="flex items-center justify-between">
                        <strong class="font-bold text-slate-900">Return Request Status:</strong>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider
                            {{ $returnRequest->status === 'completed' ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : ($returnRequest->status === 'approved' ? 'bg-blue-100 text-blue-800 border border-blue-200' : ($returnRequest->status === 'rejected' ? 'bg-rose-100 text-rose-800 border border-rose-200' : 'bg-amber-100 text-amber-800 border border-amber-200')) }}">
                            {{ $returnRequest->status }}
                        </span>
                    </div>
                    <p class="text-slate-500">Reason: <strong class="text-slate-700">{{ ucfirst(str_replace('_', ' ', $returnRequest->reason_code)) }}</strong></p>
                    @if($returnRequest->admin_notes)
                        <p class="text-slate-700 bg-white p-2.5 rounded-xl border border-slate-200 font-medium">
                            <span class="text-slate-400 block text-[10px] uppercase font-bold">Support Note:</span>
                            {{ $returnRequest->admin_notes }}
                        </p>
                    @endif
                </div>
            @elseif($order->order_status === \App\Enums\OrderStatus::Delivered)
                <button 
                    type="button" 
                    onclick="document.getElementById('return-modal').showModal()"
                    class="w-full py-3 bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs rounded-2xl transition cursor-pointer flex items-center justify-center gap-2 border border-slate-200"
                >
                    <svg class="w-4 h-4 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                    <span>Request Return / Replacement</span>
                </button>
            @endif
        </div>
    </div>

    <!-- Quick Help & Support Card -->
    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 text-xs flex items-center justify-between gap-3">
        <div>
            <strong class="font-bold text-slate-900 block">Need help with your order?</strong>
            <span class="text-slate-500 text-[11px]">Dharan dark store dispatch helpline</span>
        </div>
        <a href="tel:+977025520000" class="px-3 py-1.5 bg-white border border-slate-300 hover:bg-slate-100 rounded-xl font-bold text-slate-800 transition">
            Call Support
        </a>
    </div>

    <!-- Native HTML5 Dialog: Return Request Modal -->
    <dialog id="return-modal" class="rounded-3xl p-0 w-full max-w-md shadow-2xl backdrop:bg-slate-950/60 border-0 m-auto">
        <div class="p-6 bg-white space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div>
                    <h3 class="font-extrabold text-sm text-slate-950">Return / Replacement Request</h3>
                    <p class="text-xs text-slate-500 font-mono">#{{ $order->order_number }}</p>
                </div>
                <button type="button" onclick="document.getElementById('return-modal').close()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-900 flex items-center justify-center font-bold text-sm cursor-pointer transition">✕</button>
            </div>

            <form action="{{ route('storefront.order.return', $order->order_number) }}" method="POST" class="space-y-4 text-xs">
                @csrf
                <div>
                    <label class="block font-bold text-slate-700 mb-1.5">Reason for Return *</label>
                    <select name="reason_code" required class="w-full rounded-xl border border-slate-300 p-3 bg-white focus:border-slate-900 focus:outline-none">
                        <option value="wrong_item">Wrong Item Delivered</option>
                        <option value="damaged">Damaged / Leaking Item</option>
                        <option value="expired">Near / Past Expiry Date</option>
                        <option value="quality_issue">Quality Not as Expected</option>
                        <option value="changed_mind">Changed Mind</option>
                    </select>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1.5">Issue Explanation</label>
                    <textarea name="reason_details" rows="3" class="w-full rounded-xl border border-slate-300 p-3 focus:border-slate-900 focus:outline-none text-xs" placeholder="Please describe the issue in detail..."></textarea>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full py-3.5 bg-slate-950 hover:bg-black text-white font-extrabold rounded-2xl text-xs uppercase tracking-wider transition shadow-md cursor-pointer">
                        Submit Return Request
                    </button>
                </div>
            </form>
        </div>
    </dialog>

</div>
@endsection
