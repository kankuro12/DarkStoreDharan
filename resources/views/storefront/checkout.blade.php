@extends('layouts.storefront')

@section('content')
<div class="max-w-4xl mx-auto py-4 space-y-6">

    <!-- Header Navigation & Title -->
    <div class="flex items-center justify-between pb-4 border-b border-slate-200">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Fast Dark Store Checkout</span>
            </div>
            <h1 class="text-xl sm:text-3xl font-extrabold text-slate-950 tracking-tight">Fulfill Order in {{ $city->name }}</h1>
            <p class="text-xs text-slate-500">Local Dark Store Dispatch • Guaranteed Arrival: <strong>~{{ $city->estimated_delivery_minutes }}–45 mins</strong></p>
        </div>
        <a href="{{ route('storefront.home') }}" class="text-xs font-bold text-slate-600 hover:text-slate-950 transition flex items-center gap-1.5 bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-full">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            <span>Back to Store</span>
        </a>
    </div>

    <form action="{{ route('storefront.checkout.process') }}" method="POST" id="checkout-form" class="grid grid-cols-1 lg:grid-cols-3 gap-6 sm:gap-8">
        @csrf

        <!-- Left 2 Columns: Delivery Address & Payment Method -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- Step 1: Delivery Address & Ward -->
            <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-5">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-xl bg-slate-950 text-amber-400 font-extrabold text-xs flex items-center justify-center">1</span>
                        <h2 class="font-extrabold text-sm sm:text-base text-slate-950">Delivery Address in {{ $city->name }}</h2>
                    </div>
                    <span class="text-[11px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 rounded-full">
                        Direct Hub Route
                    </span>
                </div>

                @if(isset($savedAddresses) && $savedAddresses->isNotEmpty())
                    <!-- Saved Address Picker (Amazon-style: reuse a previous delivery address) -->
                    <div class="space-y-2.5" id="saved-address-picker">
                        <label class="block text-xs font-bold text-slate-700">Choose a saved address</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            @foreach($savedAddresses as $saved)
                                <label class="flex items-start gap-2.5 p-3.5 rounded-2xl border cursor-pointer transition has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50/50 has-[:checked]:ring-2 has-[:checked]:ring-amber-500/20 border-slate-200 hover:border-slate-300">
                                    <input
                                        type="radio"
                                        name="saved_address_choice"
                                        value="{{ $saved->id }}"
                                        class="mt-1 text-amber-500 focus:ring-amber-500"
                                        onchange="useSavedAddress({{ $saved->id }})"
                                        {{ $loop->first ? 'checked' : '' }}
                                    >
                                    <div class="min-w-0">
                                        <span class="text-xs font-bold text-slate-900 block truncate">{{ $saved->full_name }}</span>
                                        <span class="text-[11px] text-slate-500 block">{{ $saved->phone }}</span>
                                        <span class="text-[11px] text-slate-500 leading-relaxed">{{ $saved->formatted_address }}</span>
                                    </div>
                                </label>
                            @endforeach
                            <label class="flex items-center gap-2.5 p-3.5 rounded-2xl border cursor-pointer transition has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50/50 has-[:checked]:ring-2 has-[:checked]:ring-amber-500/20 border-slate-200 hover:border-slate-300">
                                <input type="radio" name="saved_address_choice" value="new" class="text-amber-500 focus:ring-amber-500" onchange="useNewAddress()">
                                <span class="text-xs font-bold text-slate-900">+ Deliver to a new address</span>
                            </label>
                        </div>
                    </div>
                    <input type="hidden" name="address_id" id="address-id-input" value="{{ $savedAddresses->first()->id }}">
                @endif

                <div id="new-address-fields" class="space-y-4 {{ isset($savedAddresses) && $savedAddresses->isNotEmpty() ? 'hidden' : '' }}">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1.5">Full Name *</label>
                        <input type="text" name="full_name" value="{{ old('full_name', auth()->user()?->name) }}" required class="w-full rounded-xl border border-slate-300 p-3 focus:border-slate-900 focus:ring-1 focus:ring-slate-900 focus:outline-none text-xs transition" placeholder="e.g. Suman Shrestha">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1.5">Mobile Phone *</label>
                        <input type="tel" name="phone" value="{{ old('phone', auth()->user()?->phone) }}" required class="w-full rounded-xl border border-slate-300 p-3 focus:border-slate-900 focus:ring-1 focus:ring-slate-900 focus:outline-none text-xs transition" placeholder="98XXXXXXXX">
                    </div>
                </div>

                @if($zones->isNotEmpty())
                    <div class="text-xs">
                        <label class="block font-bold text-slate-700 mb-1.5">Select Delivery Zone / Ward</label>
                        <select name="delivery_zone_id" class="w-full rounded-xl border border-slate-300 p-3 focus:border-slate-900 focus:ring-1 focus:ring-slate-900 focus:outline-none text-xs bg-white cursor-pointer transition">
                            @foreach($zones as $zone)
                                <option value="{{ $zone->id }}" {{ old('delivery_zone_id') == $zone->id ? 'selected' : '' }}>
                                    {{ $zone->name }} (Rs {{ number_format($zone->delivery_fee) }} delivery • ~{{ $zone->estimated_minutes }} mins)
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1.5">Area / Chowk / Ward *</label>
                        <input type="text" name="area" value="{{ old('area') }}" required class="w-full rounded-xl border border-slate-300 p-3 focus:border-slate-900 focus:ring-1 focus:ring-slate-900 focus:outline-none text-xs transition" placeholder="e.g. Bhanuchowk / Ward 3">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1.5">Street Address / House *</label>
                        <input type="text" name="street" value="{{ old('street') }}" required class="w-full rounded-xl border border-slate-300 p-3 focus:border-slate-900 focus:ring-1 focus:ring-slate-900 focus:outline-none text-xs transition" placeholder="e.g. Putali Line, House 4B">
                    </div>
                </div>

                <div class="text-xs">
                    <label class="block font-bold text-slate-700 mb-1.5">Nearest Landmark (Optional)</label>
                    <input type="text" name="landmark" value="{{ old('landmark') }}" class="w-full rounded-xl border border-slate-300 p-3 focus:border-slate-900 focus:ring-1 focus:ring-slate-900 focus:outline-none text-xs transition" placeholder="e.g. Opposite BPKIHS Main Gate, Near Clock Tower">
                </div>

                <!-- Quick Delivery Instructions Chips (Zepto/Blinkit Pattern) -->
                <div class="space-y-2 text-xs">
                    <label class="block font-bold text-slate-700">Rider Delivery Instructions (Tap to append)</label>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" onclick="appendInstruction('Leave at doorstep')" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[11px] font-semibold transition cursor-pointer">
                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                            Leave at doorstep
                        </button>
                        <button type="button" onclick="appendInstruction('Do not ring bell')" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[11px] font-semibold transition cursor-pointer">
                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            Do not ring bell
                        </button>
                        <button type="button" onclick="appendInstruction('Call upon gate arrival')" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[11px] font-semibold transition cursor-pointer">
                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            Call upon gate arrival
                        </button>
                        <button type="button" onclick="appendInstruction('Leave with building security')" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[11px] font-semibold transition cursor-pointer">
                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            Leave with guard
                        </button>
                    </div>
                    <textarea id="delivery-notes-input" name="delivery_notes" rows="2" class="w-full rounded-xl border border-slate-300 p-3 focus:border-slate-900 focus:ring-1 focus:ring-slate-900 focus:outline-none text-xs transition" placeholder="e.g. Please ring doorbell twice or call upon gate arrival.">{{ old('delivery_notes') }}</textarea>
                </div>
                </div>
            </div>

            <!-- Tip Your Delivery Partner (Blinkit/Zepto Core Feature) -->
            <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-3">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/></svg>
                        <h3 class="font-extrabold text-xs uppercase tracking-wider text-slate-950">Tip Your Delivery Partner</h3>
                    </div>
                    <span class="text-[10px] text-slate-500 font-semibold">100% tip goes to rider</span>
                </div>
                <p class="text-xs text-slate-500">Thank your rider for bringing groceries in 30 minutes in all weather conditions.</p>
                
                <div class="flex flex-wrap gap-2.5 pt-1">
                    <button type="button" onclick="selectTip(0, this)" class="tip-btn px-4 py-2 rounded-xl text-xs font-bold border border-slate-950 bg-slate-950 text-white cursor-pointer transition">
                        No Tip
                    </button>
                    <button type="button" onclick="selectTip(20, this)" class="tip-btn px-4 py-2 rounded-xl text-xs font-bold border border-slate-200 hover:border-slate-300 text-slate-700 bg-white cursor-pointer transition">
                        Rs 20
                    </button>
                    <button type="button" onclick="selectTip(30, this)" class="tip-btn px-4 py-2 rounded-xl text-xs font-bold border border-slate-200 hover:border-slate-300 text-slate-700 bg-white cursor-pointer transition">
                        Rs 30 <span class="text-[10px] text-amber-600 font-semibold ml-1">Popular</span>
                    </button>
                    <button type="button" onclick="selectTip(50, this)" class="tip-btn px-4 py-2 rounded-xl text-xs font-bold border border-slate-200 hover:border-slate-300 text-slate-700 bg-white cursor-pointer transition">
                        Rs 50 <span class="text-[10px] text-emerald-600 font-semibold ml-1">Hero</span>
                    </button>
                </div>
                <input type="hidden" id="tip-amount-input" name="rider_tip" value="0">
            </div>

            <!-- Step 2: Payment Method -->
            <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-xl bg-slate-950 text-amber-400 font-extrabold text-xs flex items-center justify-center">2</span>
                        <h2 class="font-extrabold text-sm sm:text-base text-slate-950">Payment Method</h2>
                    </div>
                    <span class="text-[11px] font-semibold text-slate-500">
                        Zero transaction fees
                    </span>
                </div>

                <div class="space-y-3">
                    <!-- COD Option with Multi-Level Rule Engine Feedback -->
                    <label class="flex items-start gap-3.5 p-4 rounded-2xl border transition-all duration-150 {{ $paymentMethods['cod'] ? 'border-slate-200 hover:border-slate-300 hover:bg-slate-50 cursor-pointer has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50/50 has-[:checked]:ring-2 has-[:checked]:ring-amber-500/20' : 'border-slate-200 bg-slate-50 opacity-60 cursor-not-allowed' }}">
                        <input 
                            type="radio" 
                            name="payment_method" 
                            value="cod" 
                            {{ $paymentMethods['cod'] ? 'checked' : 'disabled' }} 
                            class="mt-1 text-amber-500 focus:ring-amber-500"
                        >
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-xs text-slate-900">Cash on Delivery (COD)</span>
                                    <span class="text-[10px] text-slate-500">Cash or QR to Rider</span>
                                </div>
                                @if($paymentMethods['cod'])
                                    <span class="text-[10px] bg-emerald-100 text-emerald-800 font-bold px-2 py-0.5 rounded-full border border-emerald-200">Available</span>
                                @else
                                    <span class="text-[10px] bg-rose-100 text-rose-800 font-bold px-2 py-0.5 rounded-full border border-rose-200">Disabled</span>
                                @endif
                            </div>
                            <p class="text-[11px] text-slate-500 mt-0.5">Pay in cash or scan rider's QR code at your doorstep upon delivery.</p>
                            
                            @if(! $paymentMethods['cod'] && ! empty($paymentMethods['cod_disabled_reasons']))
                                <p class="text-[11px] font-semibold text-rose-600 mt-1.5 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                    <span>{{ implode(' ', $paymentMethods['cod_disabled_reasons']) }}</span>
                                </p>
                            @endif
                        </div>
                    </label>

                    <!-- eSewa Mobile Wallet -->
                    <label class="flex items-start gap-3.5 p-4 rounded-2xl border border-slate-200 hover:border-slate-300 hover:bg-slate-50 cursor-pointer transition-all duration-150 has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50/50 has-[:checked]:ring-2 has-[:checked]:ring-emerald-500/20">
                        <input type="radio" name="payment_method" value="esewa" class="mt-1 text-emerald-600 focus:ring-emerald-500">
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-xs text-slate-900">eSewa Mobile Wallet</span>
                                <span class="text-[10px] bg-emerald-100 text-emerald-800 font-bold px-2 py-0.5 rounded-full border border-emerald-200">Instant</span>
                            </div>
                            <p class="text-[11px] text-slate-500 mt-0.5">Pay seamlessly using your registered eSewa balance or linked bank.</p>
                        </div>
                    </label>

                    <!-- Khalti Wallet -->
                    <label class="flex items-start gap-3.5 p-4 rounded-2xl border border-slate-200 hover:border-slate-300 hover:bg-slate-50 cursor-pointer transition-all duration-150 has-[:checked]:border-purple-500 has-[:checked]:bg-purple-50/50 has-[:checked]:ring-2 has-[:checked]:ring-purple-500/20">
                        <input type="radio" name="payment_method" value="khalti" class="mt-1 text-purple-600 focus:ring-purple-500">
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-xs text-slate-900">Khalti Digital Wallet</span>
                                <span class="text-[10px] bg-purple-100 text-purple-800 font-bold px-2 py-0.5 rounded-full border border-purple-200">Instant</span>
                            </div>
                            <p class="text-[11px] text-slate-500 mt-0.5">Pay online instantly using Khalti wallet, eBanking, or connectIPS.</p>
                        </div>
                    </label>

                    <!-- Fonepay QR -->
                    <label class="flex items-start gap-3.5 p-4 rounded-2xl border border-slate-200 hover:border-slate-300 hover:bg-slate-50 cursor-pointer transition-all duration-150 has-[:checked]:border-rose-500 has-[:checked]:bg-rose-50/50 has-[:checked]:ring-2 has-[:checked]:ring-rose-500/20">
                        <input type="radio" name="payment_method" value="fonepay" class="mt-1 text-rose-600 focus:ring-rose-500">
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-xs text-slate-900">Fonepay Interoperable QR</span>
                                <span class="text-[10px] bg-rose-100 text-rose-800 font-bold px-2 py-0.5 rounded-full border border-rose-200">All Banks</span>
                            </div>
                            <p class="text-[11px] text-slate-500 mt-0.5">Scan & pay using any Nepali mobile banking app (Global, Nabil, NIC, Sanima, etc.).</p>
                        </div>
                    </label>
                </div>
            </div>

        </div>

        <!-- Right 1 Column: Sticky Order Summary & Place Order CTA -->
        <div class="space-y-4">
            <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-4 sticky top-20">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="font-extrabold text-sm text-slate-950">
                        Order Summary
                    </h3>
                    <span class="text-xs font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">
                        {{ $cart['item_count'] }} items
                    </span>
                </div>

                <!-- Cart Items Snapshot -->
                <div class="space-y-2.5 max-h-56 overflow-y-auto pr-1 text-xs divide-y divide-slate-100">
                    @foreach($cart['items'] as $item)
                        <div class="pt-2.5 flex justify-between gap-3">
                            <div class="min-w-0">
                                <span class="font-bold text-slate-900 block truncate">{{ $item['product_name'] }}</span>
                                <span class="text-slate-500 text-[11px]">Qty: {{ $item['quantity'] }} × Rs {{ number_format($item['unit_price']) }}</span>
                            </div>
                            <span class="font-extrabold text-slate-950 font-mono whitespace-nowrap">Rs {{ number_format($item['total']) }}</span>
                        </div>
                    @endforeach
                </div>

                <!-- Financial Totals -->
                <div class="space-y-2 pt-3 border-t border-slate-100 text-xs text-slate-600">
                    <div class="flex justify-between">
                        <span>Items Subtotal</span>
                        <span class="font-semibold text-slate-900 font-mono">Rs {{ number_format($cart['subtotal']) }}</span>
                    </div>

                    @if($cart['discount'] > 0)
                        <div class="flex justify-between text-emerald-700 font-semibold">
                            <span>Coupon Discount</span>
                            <span class="font-mono">- Rs {{ number_format($cart['discount']) }}</span>
                        </div>
                    @endif

                    <div class="flex justify-between">
                        <span>Delivery Fee</span>
                        <span class="font-semibold {{ $cart['delivery_fee'] == 0 ? 'text-emerald-700' : 'text-slate-900' }} font-mono">
                            {{ $cart['delivery_fee'] == 0 ? 'FREE' : 'Rs ' . number_format($cart['delivery_fee']) }}
                        </span>
                    </div>

                    <div class="flex justify-between" id="tip-summary-row" style="display: none;">
                        <span>Rider Tip</span>
                        <span id="tip-amount-display" class="font-mono font-semibold text-slate-900">Rs 0</span>
                    </div>

                    <!-- Promo Voucher Input Strip -->
                    <div class="pt-2 border-t border-slate-100">
                        <label class="block text-[11px] font-bold text-slate-700 mb-1">Promo Coupon Code</label>
                        <div class="flex gap-2 text-xs">
                            <input 
                                type="text" 
                                name="coupon_code" 
                                placeholder="e.g. WELCOME50" 
                                value="{{ old('coupon_code', $cart['coupon_code'] ?? '') }}"
                                class="flex-1 rounded-xl border border-slate-300 p-2 uppercase font-mono text-xs focus:border-slate-900 focus:outline-none"
                            >
                            <span class="px-3 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl text-xs flex items-center justify-center">
                                Auto-Apply
                            </span>
                        </div>
                    </div>

                    <div class="flex justify-between text-base font-black text-slate-950 pt-2.5 border-t border-slate-200">
                        <span>Grand Total</span>
                        <span id="grand-total-display" class="font-mono">Rs {{ number_format($cart['grand_total']) }}</span>
                    </div>
                </div>

                <!-- Submit Button -->
                <button 
                    type="submit" 
                    class="w-full py-4 bg-slate-950 hover:bg-black text-white font-extrabold text-xs rounded-2xl uppercase tracking-wider transition shadow-md cursor-pointer active:scale-98 flex items-center justify-center gap-2"
                >
                    <span>Place Order & Dispatch</span>
                    <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </button>

                <div class="pt-2 text-center space-y-1">
                    <p class="text-[11px] text-slate-500 font-medium">
                        Dispatched directly from {{ $city->name }} Dark Store.
                    </p>
                    <p class="text-[10px] text-slate-600">
                        100% refund guarantee if cancelled before packing.
                    </p>
                </div>
            </div>
        </div>

    </form>

</div>

<script>
    const baseTotal = {{ (float) $cart['grand_total'] }};
    let currentTip = 0;

    function toggleNewAddressFieldsRequired(isRequired) {
        const fields = document.querySelectorAll('#new-address-fields [name="full_name"], #new-address-fields [name="area"], #new-address-fields [name="street"], #new-address-fields [name="phone"]');
        fields.forEach(field => {
            if (isRequired) {
                field.setAttribute('required', 'required');
            } else {
                field.removeAttribute('required');
            }
        });
    }

    function useSavedAddress(addressId) {
        document.getElementById('address-id-input').value = addressId;
        document.getElementById('new-address-fields').classList.add('hidden');
        toggleNewAddressFieldsRequired(false);
    }

    function useNewAddress() {
        document.getElementById('address-id-input').value = '';
        document.getElementById('new-address-fields').classList.remove('hidden');
        toggleNewAddressFieldsRequired(true);
    }

    @if(isset($savedAddresses) && $savedAddresses->isNotEmpty())
        // A saved address is pre-selected on load, so the hidden manual fields start non-required.
        toggleNewAddressFieldsRequired(false);
    @endif

    function appendInstruction(text) {
        const textarea = document.getElementById('delivery-notes-input');
        if (! textarea) return;
        if (textarea.value.trim().length > 0) {
            textarea.value += ', ' + text;
        } else {
            textarea.value = text;
        }
    }

    function selectTip(amount, btnEl) {
        currentTip = amount;
        document.getElementById('tip-amount-input').value = amount;

        const tipRow = document.getElementById('tip-summary-row');
        const tipDisplay = document.getElementById('tip-amount-display');
        const totalDisplay = document.getElementById('grand-total-display');

        if (amount > 0) {
            tipRow.style.display = 'flex';
            tipDisplay.innerText = 'Rs ' + amount;
        } else {
            tipRow.style.display = 'none';
        }

        const newTotal = baseTotal + currentTip;
        totalDisplay.innerText = 'Rs ' + newTotal.toLocaleString();

        document.querySelectorAll('.tip-btn').forEach(btn => {
            btn.classList.remove('border-slate-950', 'bg-slate-950', 'text-white');
            btn.classList.add('border-slate-200', 'bg-white', 'text-slate-700');
        });

        btnEl.classList.add('border-slate-950', 'bg-slate-950', 'text-white');
        btnEl.classList.remove('border-slate-200', 'bg-white', 'text-slate-700');
    }
</script>
@endsection
