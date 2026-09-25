{{-- Why Shop From DarkStore: reused on the pre-city landing screen and the in-city homepage --}}
<div class="max-w-5xl mx-auto bg-white rounded-3xl p-6 sm:p-10 border border-slate-200 shadow-xs space-y-6 {{ $wrapperClass ?? 'mt-8' }}">
    <div class="text-center max-w-xl mx-auto space-y-2">
        <h3 class="text-lg sm:text-2xl font-extrabold text-slate-950">Why Shop From DarkStore{{ isset($cityName) ? ' ' . $cityName : '' }}?</h3>
        <p class="text-xs text-slate-500">Built for fast, reliable grocery delivery in {{ $cityName ?? 'Dharan' }} and the eastern hubs.</p>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 sm:gap-6 pt-2">
        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-2">
            <div class="w-8 h-8 rounded-xl bg-amber-500 text-white flex items-center justify-center font-bold">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
            </div>
            <h4 class="font-bold text-xs text-slate-950">15–30 Min Delivery</h4>
            <p class="text-[11px] text-slate-500 leading-relaxed">Local micro-warehouses close to your neighborhood for rapid dispatch.</p>
        </div>

        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-2">
            <div class="w-8 h-8 rounded-xl bg-emerald-500 text-white flex items-center justify-center font-bold">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
            </div>
            <h4 class="font-bold text-xs text-slate-950">Cold-Chain Fresh</h4>
            <p class="text-[11px] text-slate-500 leading-relaxed">Dairy, cold drinks & perishables kept at the right temperature until they reach you.</p>
        </div>

        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-2">
            <div class="w-8 h-8 rounded-xl bg-blue-500 text-white flex items-center justify-center font-bold">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
            </div>
            <h4 class="font-bold text-xs text-slate-950">Accurate Stock</h4>
            <p class="text-[11px] text-slate-500 leading-relaxed">Live inventory means what you see is what's actually available to order.</p>
        </div>

        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-2">
            <div class="w-8 h-8 rounded-xl bg-purple-500 text-white flex items-center justify-center font-bold">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
            </div>
            <h4 class="font-bold text-xs text-slate-950">Easy Returns</h4>
            <p class="text-[11px] text-slate-500 leading-relaxed">24h instant replacement or full refund for damaged or incorrect items.</p>
        </div>
    </div>
</div>
