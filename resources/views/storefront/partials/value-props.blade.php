{{-- Why Shop From DarkStore: reused on the pre-city landing screen and the in-city homepage --}}
<div class="max-w-5xl mx-auto space-y-6 {{ $wrapperClass ?? 'mt-8' }}">
    <h3 class="text-base sm:text-lg font-bold text-slate-950 text-center">Why Shop From DarkStore{{ isset($cityName) ? ' ' . $cityName : '' }}?</h3>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
        <div class="space-y-1">
            <h4 class="font-semibold text-sm text-slate-950">15–30 min delivery</h4>
            <p class="text-xs text-slate-500 leading-relaxed">Local stores close to your neighborhood for rapid dispatch.</p>
        </div>
        <div class="space-y-1">
            <h4 class="font-semibold text-sm text-slate-950">Kept fresh</h4>
            <p class="text-xs text-slate-500 leading-relaxed">Dairy, cold drinks and perishables kept at the right temperature until they reach you.</p>
        </div>
        <div class="space-y-1">
            <h4 class="font-semibold text-sm text-slate-950">Accurate stock</h4>
            <p class="text-xs text-slate-500 leading-relaxed">Live inventory, so what you see is what's actually available in {{ $cityName ?? 'Dharan' }}.</p>
        </div>
        <div class="space-y-1">
            <h4 class="font-semibold text-sm text-slate-950">Easy returns</h4>
            <p class="text-xs text-slate-500 leading-relaxed">24h replacement or full refund for damaged or incorrect items.</p>
        </div>
    </div>
</div>
