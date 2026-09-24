@extends('layouts.storefront')

@section('content')
<!-- Schema.org JSON-LD Structured Data (§43.1) -->
@php
    $primaryVariant = $product['variants'][0] ?? null;
    $schemaData = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $product['name'],
        'image' => $product['image'],
        'description' => $product['description'],
        'brand' => [
            '@type' => 'Brand',
            'name' => $product['brand']['name'] ?? 'Local Dark Store',
        ],
        'offers' => [
            '@type' => 'Offer',
            'price' => $primaryVariant['price'] ?? 0,
            'priceCurrency' => 'NPR',
            'availability' => $product['is_available'] ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'priceValidUntil' => now()->addYear()->toDateString(),
        ],
    ];
@endphp
<script type="application/ld+json">
{!! json_encode($schemaData, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
</script>

<div class="max-w-5xl mx-auto py-4 space-y-8">

    <!-- Breadcrumb Navigation -->
    <nav class="flex items-center gap-2 text-xs font-semibold text-slate-500">
        <a href="{{ route('storefront.home') }}" class="hover:text-slate-900 transition flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
            <span>Home</span>
        </a>
        <span class="text-slate-400">/</span>
        @if($product['category'])
            <a href="{{ route('storefront.home', ['category_id' => $product['category']['id'] ?? null]) }}" class="hover:text-slate-900 transition">
                {{ $product['category']['name'] ?? 'Category' }}
            </a>
            <span class="text-slate-400">/</span>
        @endif
        <span class="text-slate-900 truncate max-w-xs">{{ $product['name'] }}</span>
    </nav>

    <!-- Product Hero Card Grid -->
    <div class="bg-white rounded-3xl p-6 sm:p-10 border border-slate-200 shadow-xs grid grid-cols-1 md:grid-cols-2 gap-8 lg:gap-12">
        
        <!-- Left Column: Product Image & Badges -->
        <div class="space-y-4">
            <div class="aspect-square bg-slate-50 rounded-2xl overflow-hidden flex items-center justify-center border border-slate-100 relative group">
                @if($product['image'])
                    <img 
                        src="{{ $product['image'] }}" 
                        alt="{{ $product['name'] }}" 
                        class="w-full h-full object-cover group-hover:scale-105 transition duration-500"
                    >
                @else
                    <div class="w-20 h-20 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center">
                        <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    </div>
                @endif

                <!-- Delivery Speed Badge (§38.5) -->
                <div class="absolute top-4 left-4 delivery-badge px-3 py-1.5 rounded-full text-xs font-bold tracking-tight inline-flex items-center gap-1.5 shadow-sm">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <svg class="w-3.5 h-3.5 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/></svg>
                    <span>{{ $product['estimated_minutes'] }} min fast delivery in {{ $product['city_name'] }}</span>
                </div>
            </div>

            <!-- Dark Store Micro-Fulfillment Features -->
            <div class="grid grid-cols-2 gap-3 pt-1">
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-100 flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                    </div>
                    <div>
                        <h4 class="text-[11px] font-bold text-slate-900">Cold Chain</h4>
                        <p class="text-[10px] text-slate-500">Chilled & sealed</p>
                    </div>
                </div>
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-100 flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                    </div>
                    <div>
                        <h4 class="text-[11px] font-bold text-slate-900">Local Hub</h4>
                        <p class="text-[10px] text-slate-500">{{ $product['city_name'] }} dark store</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Product Details & Add to Bag -->
        <div class="flex flex-col justify-between space-y-6">
            <div class="space-y-4">
                <div class="flex items-center gap-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-amber-800 bg-amber-50 border border-amber-200 px-2.5 py-1 rounded-lg">
                        {{ $product['brand']['name'] ?? 'Local Essentials' }}
                    </span>
                    @if($product['category'])
                        <span class="text-[11px] font-bold text-slate-500 bg-slate-100 px-2.5 py-1 rounded-lg">
                            {{ $product['category']['name'] ?? 'Pantry' }}
                        </span>
                    @endif
                </div>

                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-950 leading-tight">
                    {{ $product['name'] }}
                </h1>

                <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                    {{ $product['description'] }}
                </p>

                <!-- Variants Selection Strip (§6) -->
                <div class="pt-4 border-t border-slate-100 space-y-2.5">
                    <label class="block text-xs font-bold text-slate-900 uppercase tracking-wider">
                        Select Pack Size / Variant:
                    </label>
                    <div class="flex flex-wrap gap-2.5" id="variant-selector">
                        @foreach($product['variants'] as $index => $variant)
                            <button 
                                type="button"
                                onclick="selectVariant({{ $variant['id'] }}, {{ $variant['price'] }}, {{ $variant['available_stock'] }}, '{{ $variant['sku'] }}', this)"
                                class="variant-btn px-4 py-2.5 rounded-2xl text-xs font-bold border transition-all duration-150 cursor-pointer text-left {{ $index === 0 ? 'border-amber-500 bg-amber-50/70 text-slate-950 ring-2 ring-amber-500/20' : 'border-slate-200 text-slate-700 hover:border-slate-300 hover:bg-slate-50' }} {{ ! $variant['is_available'] ? 'opacity-50' : '' }}"
                            >
                                <span class="block font-bold">{{ $variant['name'] }}</span>
                                <span class="block text-[11px] text-slate-500 font-mono font-normal">Rs {{ number_format($variant['price']) }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- Price and Live City Stock Indicator -->
                <div class="pt-4 border-t border-slate-100 flex items-baseline gap-3">
                    <span id="active-price" class="text-3xl font-extrabold text-slate-950 font-mono">
                        Rs {{ number_format($primaryVariant['price'] ?? 0) }}
                    </span>
                    <span id="active-stock" class="text-xs font-bold {{ ($primaryVariant['available_stock'] ?? 0) > 0 ? 'text-emerald-800 bg-emerald-100 border border-emerald-200' : 'text-rose-800 bg-rose-100 border border-rose-200' }} px-3 py-1 rounded-full">
                        {{ ($primaryVariant['available_stock'] ?? 0) > 0 ? 'In Stock (' . ($primaryVariant['available_stock'] ?? 0) . ' units)' : 'Out of Stock in ' . $product['city_name'] }}
                    </span>
                </div>
            </div>

            <!-- Add to Cart Stepper & CTA -->
            <div class="pt-6 border-t border-slate-100 space-y-3">
                <input type="hidden" id="selected-variant-id" value="{{ $primaryVariant['id'] ?? '' }}">

                <div class="flex items-center gap-3">
                    <button 
                        type="button" 
                        id="main-add-button"
                        onclick="addActiveVariantToCart()"
                        {{ ! $product['is_available'] ? 'disabled' : '' }}
                        class="flex-1 py-4 bg-slate-950 hover:bg-black text-white font-extrabold text-sm rounded-2xl uppercase tracking-wider transition shadow-md cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 active:scale-98"
                    >
                        <svg class="w-5 h-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                        <span>{{ $product['is_available'] ? '+ Add to Basket' : 'Out of Stock' }}</span>
                    </button>
                </div>

                <div class="text-[11px] text-slate-500 flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                    <span>Fulfillment from {{ $product['city_name'] }} Dark Store with tamper-proof bag sealing.</span>
                </div>
            </div>
        </div>

    </div>

    <!-- Product Specifications & Highlights Accordion (§41) -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-4">
        <h3 class="text-sm font-extrabold text-slate-950 uppercase tracking-wider">Product Highlights & Storage</h3>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
            <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-100">
                <span class="text-[10px] text-slate-400 uppercase font-bold block mb-1">Fulfillment Hub</span>
                <span class="font-extrabold text-slate-900 block">{{ $product['city_name'] }} Central Store</span>
                <span class="text-[10px] text-emerald-700 font-semibold inline-flex items-center gap-1">
                    <svg class="w-3 h-3 text-amber-500 inline" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/></svg>
                    ~{{ $product['estimated_minutes'] }}m SLA
                </span>
            </div>
            <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-100">
                <span class="text-[10px] text-slate-400 uppercase font-bold block mb-1">Storage Condition</span>
                <span class="font-extrabold text-slate-900 block">Temperature Controlled</span>
                <span class="text-[10px] text-slate-500 font-medium">Refrigerated if dairy/beverage</span>
            </div>
            <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-100">
                <span class="text-[10px] text-slate-400 uppercase font-bold block mb-1">Return Window</span>
                <span class="font-extrabold text-slate-900 block">24 Hours Quality Check</span>
                <span class="text-[10px] text-emerald-700 font-semibold">Direct replacement</span>
            </div>
            <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-100">
                <span class="text-[10px] text-slate-400 uppercase font-bold block mb-1">Packaging</span>
                <span class="font-extrabold text-slate-900 block">Tamper-Proof Sealed</span>
                <span class="text-[10px] text-slate-500 font-medium">QR Verified Bag</span>
            </div>
        </div>
    </div>

    <!-- Related Products in Category (§41.5) -->
    @if(!empty($relatedProducts))
        <div class="space-y-4 pt-2">
            <div class="flex items-center justify-between">
                <h3 class="text-base sm:text-lg font-extrabold text-slate-950">Pairs Well With / More in Category</h3>
                <span class="text-xs font-semibold text-slate-500">Fast dispatch together</span>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
                @foreach($relatedProducts as $rel)
                    @php
                        $relVariant = $rel['variants'][0] ?? null;
                    @endphp
                    <div class="bg-white rounded-2xl p-3 border border-slate-200 hover:border-slate-300 shadow-2xs hover:shadow-md transition duration-200 flex flex-col justify-between">
                        <a href="{{ route('storefront.product', $rel['slug']) }}" class="block">
                            <div class="aspect-square bg-slate-50 rounded-xl overflow-hidden mb-2 flex items-center justify-center border border-slate-100">
                                @if(!empty($rel['image']))
                                    <img src="{{ $rel['image'] }}" alt="{{ $rel['name'] }}" class="w-full h-full object-cover" loading="lazy">
                                @else
                                    <div class="w-8 h-8 rounded-lg bg-slate-100 text-slate-400 flex items-center justify-center">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                                    </div>
                                @endif
                            </div>
                            <h4 class="text-xs font-bold text-slate-900 line-clamp-1 hover:text-amber-600 transition">{{ $rel['name'] }}</h4>
                            <span class="text-[10px] text-slate-500 block mb-2 font-mono">Rs {{ number_format($rel['primary_price']) }}</span>
                        </a>

                        @if($relVariant && $rel['is_available'])
                            <button 
                                type="button" 
                                onclick="addToCart({{ $relVariant['id'] }}, {{ $currentCity->id }}, this)"
                                class="w-full py-1.5 bg-slate-950 hover:bg-black text-white text-[11px] font-bold rounded-xl transition cursor-pointer active:scale-95"
                            >
                                + ADD
                            </button>
                        @else
                            <button disabled class="w-full py-1.5 bg-slate-100 text-slate-400 text-[10px] font-bold rounded-xl cursor-not-allowed">
                                Out
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

</div>

<script>
    let activeVariantId = {{ $primaryVariant['id'] ?? 'null' }};
    let cityId = {{ $currentCity?->id ?? 1 }};

    function selectVariant(id, price, stock, sku, btnEl) {
        activeVariantId = id;
        document.getElementById('selected-variant-id').value = id;
        document.getElementById('active-price').innerText = 'Rs ' + Number(price).toLocaleString();

        const stockEl = document.getElementById('active-stock');
        const addBtn = document.getElementById('main-add-button');

        if (stock > 0) {
            stockEl.innerText = `In Stock (${stock} units)`;
            stockEl.className = 'text-xs font-bold text-emerald-800 bg-emerald-100 border border-emerald-200 px-3 py-1 rounded-full';
            addBtn.disabled = false;
            addBtn.innerHTML = `
                <svg class="w-5 h-5 text-amber-400 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                + Add to Basket
            `;
        } else {
            stockEl.innerText = `Out of Stock in {{ $product['city_name'] }}`;
            stockEl.className = 'text-xs font-bold text-rose-800 bg-rose-100 border border-rose-200 px-3 py-1 rounded-full';
            addBtn.disabled = true;
            addBtn.innerText = 'Out of Stock';
        }

        document.querySelectorAll('.variant-btn').forEach(btn => {
            btn.classList.remove('border-amber-500', 'bg-amber-50/70', 'ring-2', 'ring-amber-500/20');
            btn.classList.add('border-slate-200');
        });

        btnEl.classList.add('border-amber-500', 'bg-amber-50/70', 'ring-2', 'ring-amber-500/20');
        btnEl.classList.remove('border-slate-200');
    }

    function addActiveVariantToCart() {
        if (! activeVariantId) return;
        const btn = document.getElementById('main-add-button');
        addToCart(activeVariantId, cityId, btn);
    }
</script>
@endsection
