@extends('layouts.storefront')

@section('content')
<div class="space-y-6 sm:space-y-8">

@if(! $currentCity)
    <!-- City Selection Welcome Screen (§3, §38.5) -->
    <div class="space-y-8 py-4 sm:py-8">
        
        <!-- Welcome Hero Banner -->
        <div class="bg-gradient-to-br from-amber-500/10 via-amber-100/40 to-slate-100 rounded-3xl p-6 sm:p-12 border border-amber-200/60 shadow-xs text-center max-w-3xl mx-auto space-y-4 relative overflow-hidden">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/90 text-amber-950 text-xs font-bold tracking-wide border border-amber-200 shadow-2xs mx-auto">
                <span class="w-2 h-2 rounded-full bg-amber-500 animate-ping"></span>
                <span class="uppercase tracking-wider text-[11px]">Instant Local Dark Stores</span>
            </div>

            <h1 class="text-3xl sm:text-5xl font-extrabold text-slate-950 tracking-tight leading-tight">
                Where should we deliver today?
            </h1>

            <p class="text-xs sm:text-sm text-slate-600 font-medium leading-relaxed max-w-xl mx-auto">
                DarkStore delivers groceries, chilled beverages, pantry staples, and daily essentials in <strong>15–30 minutes</strong> directly from neighborhood micro-warehouses. Choose your delivery city to explore localized stock, accurate delivery SLAs, and prices.
            </p>

            <!-- Decorative bolt -->
            <div class="absolute -right-6 -bottom-6 opacity-10 pointer-events-none select-none text-slate-900">
                <svg class="w-64 h-64" viewBox="0 0 24 24" fill="currentColor"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
            </div>
        </div>

        <!-- City Selection Cards Grid -->
        <div class="max-w-5xl mx-auto space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base sm:text-xl font-extrabold text-slate-950">Select Your Delivery City</h2>
                    <p class="text-xs text-slate-500">Pick your delivery destination to unlock local inventory & pricing</p>
                </div>
                <span class="text-xs font-bold text-slate-500 bg-slate-100 px-3 py-1 rounded-full border border-slate-200">
                    {{ count($cities) }} cities operational
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($cities as $city)
                    <form action="{{ route('storefront.cart.change_city') }}" method="POST" class="h-full">
                        @csrf
                        <input type="hidden" name="city_id" value="{{ $city->id }}">
                        <button 
                            type="submit" 
                            class="w-full h-full p-6 rounded-3xl bg-white hover:bg-amber-50/40 border-2 border-slate-200 hover:border-amber-500 shadow-xs hover:shadow-md transition-all duration-200 text-left cursor-pointer group flex flex-col justify-between"
                        >
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <div class="w-11 h-11 rounded-2xl bg-amber-500 text-white flex items-center justify-center font-black group-hover:scale-110 transition duration-200 shadow-sm">
                                        <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                                    </div>
                                    <span class="text-xs font-bold text-emerald-800 bg-emerald-100 border border-emerald-200 px-3 py-1 rounded-full">
                                        ~{{ $city->estimated_delivery_minutes }}m SLA
                                    </span>
                                </div>
                                <div>
                                    <h3 class="text-xl font-extrabold text-slate-950 group-hover:text-amber-600 transition">{{ $city->name }}</h3>
                                    <p class="text-xs text-slate-500">{{ $city->province ?? 'Nepal' }}</p>
                                </div>
                                <div class="pt-3 border-t border-slate-100 space-y-1.5 text-xs text-slate-600">
                                    <div class="flex justify-between">
                                        <span class="text-slate-500">Standard Delivery:</span>
                                        <span class="font-bold text-slate-900">Rs {{ number_format($city->default_delivery_fee) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-slate-500">Free Delivery:</span>
                                        <span class="font-bold text-emerald-700">&gt; Rs {{ number_format($city->free_delivery_minimum ?? 500) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-slate-500">Payment:</span>
                                        <span class="font-bold text-slate-900">COD & Online Wallets</span>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-bold text-slate-950 group-hover:text-amber-600">
                                <span>Shop in {{ $city->name }}</span>
                                <span class="group-hover:translate-x-1.5 transition duration-200">→</span>
                            </div>
                        </button>
                    </form>
                @endforeach
            </div>
        </div>

        <!-- Why DarkStore 4 Value Props -->
        <div class="max-w-5xl mx-auto bg-white rounded-3xl p-6 sm:p-10 border border-slate-200 shadow-xs space-y-6 mt-8">
            <div class="text-center max-w-xl mx-auto space-y-2">
                <h3 class="text-lg sm:text-2xl font-extrabold text-slate-950">Why Shop From DarkStore?</h3>
                <p class="text-xs text-slate-500">Engineered for ultra-fast local grocery fulfillment in Dharan & eastern hubs.</p>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 sm:gap-6 pt-2">
                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-2">
                    <div class="w-8 h-8 rounded-xl bg-amber-500 text-white flex items-center justify-center font-bold">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                    </div>
                    <h4 class="font-bold text-xs text-slate-950">15–30 Min SLA</h4>
                    <p class="text-[11px] text-slate-500 leading-relaxed">Micro-warehouses positioned right inside your ward for rapid dispatch.</p>
                </div>

                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-2">
                    <div class="w-8 h-8 rounded-xl bg-emerald-500 text-white flex items-center justify-center font-bold">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                    </div>
                    <h4 class="font-bold text-xs text-slate-950">100% Cold-Chain Fresh</h4>
                    <p class="text-[11px] text-slate-500 leading-relaxed">Dairy, cold drinks & perishables stored in strict climate-controlled bays.</p>
                </div>

                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-2">
                    <div class="w-8 h-8 rounded-xl bg-blue-500 text-white flex items-center justify-center font-bold">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                    </div>
                    <h4 class="font-bold text-xs text-slate-950">Zero Overselling</h4>
                    <p class="text-[11px] text-slate-500 leading-relaxed">Atomic pessimistic row locking reserves stock the moment you order.</p>
                </div>

                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-2">
                    <div class="w-8 h-8 rounded-xl bg-purple-500 text-white flex items-center justify-center font-bold">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                    </div>
                    <h4 class="font-bold text-xs text-slate-950">Doorstep Return Policy</h4>
                    <p class="text-[11px] text-slate-500 leading-relaxed">24h instant replacement or full refund for damaged items.</p>
                </div>
            </div>
        </div>
    </div>
@else
    <!-- Dynamic Banners or Fallback Hero -->
    @if(!empty($banners))
        <div class="flex gap-4 overflow-x-auto snap-x snap-mandatory scrollbar-none pb-2 -mx-4 px-4 sm:mx-0 sm:px-0">
            @foreach($banners as $banner)
                <div class="snap-center shrink-0 w-full sm:w-[85%] md:w-3/4 rounded-3xl overflow-hidden relative shadow-sm border border-slate-200">
                    @if(!empty($banner['link_url']))
                        <a href="{{ $banner['link_url'] }}" class="block">
                    @endif
                    <img src="{{ $banner['image_url'] }}" alt="Promo Banner" class="w-full h-40 sm:h-64 object-cover">
                    @if(!empty($banner['link_url']))
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <!-- Hero Editorial Promo Banner Fallback -->
        <div class="bg-gradient-to-br from-amber-500/10 via-amber-100/40 to-slate-100 rounded-3xl p-6 sm:p-10 border border-amber-200/60 shadow-xs relative overflow-hidden">
            <div class="max-w-xl space-y-3 relative z-10">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/90 text-amber-950 text-xs font-bold tracking-wide border border-amber-200 shadow-2xs">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span class="uppercase tracking-wider text-[11px]">Instant Local Dark Store</span>
                </div>
                
                <h1 class="text-2xl sm:text-4xl lg:text-5xl font-extrabold text-slate-950 tracking-tight leading-tight">
                    Daily Essentials at your Door in <span class="text-amber-600 underline decoration-amber-400 decoration-wavy underline-offset-4">{{ $currentCity->name }}</span>.
                </h1>
                
                <p class="text-xs sm:text-sm text-slate-600 font-medium leading-relaxed max-w-lg">
                    Fresh dairy, chilled drinks, pantry essentials & quick snacks dispatched cold and fresh in <strong>{{ $currentCity->estimated_delivery_minutes ?? 30 }} minutes</strong> directly from neighborhood micro-warehouses.
                </p>

                <div class="pt-2 flex flex-wrap items-center gap-3 text-[11px] font-semibold text-slate-700">
                    <span class="inline-flex items-center gap-1.5 bg-white/80 px-2.5 py-1 rounded-lg border border-slate-200">
                        <svg class="w-3.5 h-3.5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                        <span>~{{ $currentCity->estimated_delivery_minutes ?? 30 }}m Delivery SLA</span>
                    </span>
                    <span class="inline-flex items-center gap-1.5 bg-white/80 px-2.5 py-1 rounded-lg border border-slate-200">
                        <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                        <span>Free Delivery &gt; Rs {{ number_format($currentCity->free_delivery_minimum ?? 500) }}</span>
                    </span>
                    <span class="inline-flex items-center gap-1.5 bg-white/80 px-2.5 py-1 rounded-lg border border-slate-200">
                        <svg class="w-3.5 h-3.5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>COD & Online Wallets</span>
                    </span>
                </div>
            </div>

            <div class="absolute -right-6 -bottom-6 opacity-10 pointer-events-none select-none text-slate-900">
                <svg class="w-72 h-72 sm:w-96 sm:h-96" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>
                </svg>
            </div>
        </div>
    @endif

    <!-- Category Filter Chips Strip (§41.5) -->
    <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none">
        <a 
            href="{{ route('storefront.home', array_filter(['city_id' => $currentCity->id, 'q' => request('q')])) }}"
            class="px-4 py-2 rounded-full text-xs font-bold whitespace-nowrap transition cursor-pointer flex items-center gap-1.5 {{ !request('category_id') ? 'bg-slate-950 text-white shadow-xs' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200' }}"
        >
            <span>All Items</span>
        </a>
        @foreach($categories as $category)
            <a 
                href="{{ route('storefront.home', array_filter(['category_id' => $category->id, 'city_id' => $currentCity->id, 'q' => request('q')])) }}"
                class="px-4 py-2 rounded-full text-xs font-bold whitespace-nowrap transition cursor-pointer flex items-center gap-1.5 {{ request('category_id') == $category->id ? 'bg-slate-950 text-white shadow-xs' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200' }}"
            >
                <span>{{ $category->name }}</span>
                <span class="text-[10px] opacity-75 font-mono">({{ $category->products_count }})</span>
            </a>
        @endforeach
    </div>

    <!-- Featured Products (§41.4) -->
    @if(!empty($featuredProducts) && !request('q') && !request('category_id'))
        <div class="mt-8 mb-4">
            <h2 class="text-lg sm:text-xl font-extrabold text-slate-950 tracking-tight flex items-center gap-2 mb-4">
                <span>Featured in {{ $currentCity->name }}</span>
                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
            </h2>
            <div class="flex gap-4 overflow-x-auto snap-x scrollbar-none pb-4 -mx-4 px-4 sm:mx-0 sm:px-0">
                @foreach($featuredProducts as $product)
                    @php
                        $primaryVariant = $product['variants'][0] ?? null;
                        $hasStock = $product['is_available'];
                        $isSale = $product['is_sale'];
                        $regPrice = $product['primary_regular_price'] ?? $product['primary_price'];
                        $currPrice = $product['primary_price'];
                        $discountPct = ($regPrice > $currPrice) ? round((($regPrice - $currPrice) / $regPrice) * 100) : 0;
                        $stockAvailable = $primaryVariant['available_stock'] ?? 0;
                        $inCartItem = collect($cartDetailed['items'] ?? [])->firstWhere('variant_id', $primaryVariant['id'] ?? null);
                        $inCartQty = $inCartItem['quantity'] ?? 0;
                    @endphp
                    <div class="snap-start shrink-0 w-40 sm:w-48 bg-white rounded-2xl p-3 border border-slate-200 hover:border-slate-300 shadow-2xs hover:shadow-md transition flex flex-col group relative">
                        <div class="flex items-center justify-between gap-1 mb-2">
                            <span class="delivery-badge px-2 py-0.5 rounded-md text-[10px] font-bold tracking-tight inline-flex items-center gap-1">
                                <svg class="w-3 h-3 text-emerald-600" viewBox="0 0 24 24" fill="currentColor"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                                <span>{{ $product['estimated_minutes'] }}m</span>
                            </span>
                            @if(! $hasStock)
                                <span class="bg-rose-100 text-rose-800 border border-rose-200 px-1.5 py-0.5 rounded text-[9px] font-black uppercase">Sold Out</span>
                            @elseif($discountPct > 0)
                                <span class="bg-amber-100 text-amber-900 border border-amber-200 px-1.5 py-0.5 rounded text-[9px] font-black">{{ $discountPct }}% OFF</span>
                            @endif
                        </div>
                        <a href="{{ route('storefront.product', $product['slug']) }}" class="block cursor-pointer flex-1">
                            <div class="aspect-square bg-slate-50 rounded-xl overflow-hidden mb-2.5 flex items-center justify-center relative border border-slate-100">
                                @if(!empty($product['image']))
                                    <img src="{{ $product['image'] }}" alt="{{ $product['name'] }}" class="w-full h-full object-cover group-hover:scale-105 transition {{ ! $hasStock ? 'grayscale opacity-50' : '' }}" loading="lazy">
                                @else
                                    <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-400 flex items-center justify-center"><svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg></div>
                                @endif
                            </div>
                            <div class="space-y-1 mb-3">
                                <h3 class="text-xs font-bold text-slate-900 line-clamp-2 leading-snug group-hover:text-amber-600">{{ $product['name'] }}</h3>
                            </div>
                        </a>
                        <div class="pt-2 border-t border-slate-100 flex items-center justify-between mt-auto">
                            <div>
                                <span class="text-xs font-extrabold text-slate-950 block font-mono">Rs {{ number_format($product['primary_price']) }}</span>
                            </div>
                            @if($hasStock && $primaryVariant)
                                @if($inCartQty > 0)
                                    <div class="flex items-center gap-1.5 bg-slate-950 text-white rounded-xl px-1.5 py-1 text-xs font-bold shadow-2xs">
                                        <form action="{{ route('storefront.cart.update') }}" method="POST" class="inline">@csrf<input type="hidden" name="variant_id" value="{{ $primaryVariant['id'] }}"><input type="hidden" name="quantity" value="{{ $inCartQty - 1 }}"><button type="submit" class="px-1 text-amber-400 font-black">-</button></form>
                                        <span class="font-mono text-[10px]">{{ $inCartQty }}</span>
                                        <form action="{{ route('storefront.cart.update') }}" method="POST" class="inline">@csrf<input type="hidden" name="variant_id" value="{{ $primaryVariant['id'] }}"><input type="hidden" name="quantity" value="{{ $inCartQty + 1 }}"><button type="submit" class="px-1 text-amber-400 font-black">+</button></form>
                                    </div>
                                @else
                                    <button type="button" onclick="addToCart({{ $primaryVariant['id'] }}, {{ $currentCity->id }}, this)" class="px-3 py-1.5 rounded-xl bg-slate-950 text-white text-xs font-bold active:scale-95">ADD</button>
                                @endif
                            @else
                                <button disabled class="px-2 py-1 rounded-xl bg-slate-100 text-slate-400 text-[10px] font-bold">Out</button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Product Grid Section (§33, §38.5, §41.4, §41.5) -->
    <div>
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-base sm:text-xl font-extrabold text-slate-950 tracking-tight flex items-center gap-2">
                    @if(request('q'))
                        <span>Search results for "{{ request('q') }}"</span>
                    @elseif(request('category_id'))
                        <span>Category Products</span>
                    @else
                        <span>Fast Delivery in {{ $currentCity->name }}</span>
                    @endif
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                </h2>
                <p class="text-xs text-slate-500">Local stock available for immediate 30-min picking & dispatch</p>
            </div>
            <span class="text-xs font-bold text-slate-500 bg-slate-100 px-3 py-1 rounded-full border border-slate-200">
                {{ count($products) }} items
            </span>
        </div>

        @if(empty($products))
            <div class="py-20 text-center space-y-4 bg-white rounded-3xl border border-slate-200 p-8 shadow-xs">
                <div class="w-16 h-16 rounded-2xl bg-amber-50 text-amber-500 flex items-center justify-center mx-auto">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                </div>
                <h3 class="text-base font-bold text-slate-900">No products available in this view</h3>
                <p class="text-xs text-slate-500 max-w-sm mx-auto">
                    Items are strictly localized to each city's dark store. Try selecting another category or clearing your filters.
                </p>
                <button 
                    onclick="document.getElementById('city-modal').showModal()"
                    class="mt-2 px-5 py-2.5 bg-slate-950 hover:bg-black text-white font-bold rounded-xl text-xs uppercase tracking-wider transition cursor-pointer shadow-xs"
                >
                    Change Delivery City
                </button>
            </div>
        @else
            <!-- Responsive Grid: 2 columns on Mobile, 3 on Tablet, 4-5 on Desktop (§41.4) -->
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 sm:gap-4.5">
                @foreach($products as $product)
                    @php
                        $primaryVariant = $product['variants'][0] ?? null;
                        $hasStock = $product['is_available'];
                        $isSale = $product['is_sale'];
                        $regPrice = $product['primary_regular_price'] ?? $product['primary_price'];
                        $currPrice = $product['primary_price'];
                        $discountPct = ($regPrice > $currPrice) ? round((($regPrice - $currPrice) / $regPrice) * 100) : 0;
                        $stockAvailable = $primaryVariant['available_stock'] ?? 0;
                        $inCartItem = collect($cartDetailed['items'] ?? [])->firstWhere('variant_id', $primaryVariant['id'] ?? null);
                        $inCartQty = $inCartItem['quantity'] ?? 0;
                    @endphp
                    <div class="bg-white rounded-2xl p-3 sm:p-3.5 border border-slate-200 hover:border-slate-300 shadow-2xs hover:shadow-md transition-all duration-200 flex flex-col justify-between group relative">
                        
                        <!-- Top Tag: Delivery SLA & Stock State -->
                        <div class="flex items-center justify-between gap-1 mb-2">
                            <span class="delivery-badge px-2 py-0.5 rounded-md text-[10px] font-bold tracking-tight inline-flex items-center gap-1">
                                <svg class="w-3 h-3 text-emerald-600" viewBox="0 0 24 24" fill="currentColor"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                                <span>{{ $product['estimated_minutes'] }}m</span>
                            </span>
                            @if(! $hasStock)
                                <span class="bg-rose-100 text-rose-800 border border-rose-200 px-1.5 py-0.5 rounded text-[9px] font-black uppercase tracking-wider">
                                    Sold Out
                                </span>
                            @elseif($stockAvailable > 0 && $stockAvailable <= 5)
                                <span class="bg-amber-100 text-amber-900 border border-amber-200 px-1.5 py-0.5 rounded text-[9px] font-black">
                                    Only {{ $stockAvailable }} left
                                </span>
                            @elseif($discountPct > 0)
                                <span class="bg-amber-100 text-amber-900 border border-amber-200 px-1.5 py-0.5 rounded text-[9px] font-black">
                                    {{ $discountPct }}% OFF
                                </span>
                            @endif
                        </div>

                        <!-- Product Thumbnail & Links -->
                        <a href="{{ route('storefront.product', $product['slug']) }}" class="block cursor-pointer">
                            <div class="aspect-square bg-slate-50 rounded-xl overflow-hidden mb-2.5 flex items-center justify-center relative border border-slate-100">
                                @if(!empty($product['image']))
                                    <img 
                                        src="{{ $product['image'] }}" 
                                        alt="{{ $product['name'] }}" 
                                        class="w-full h-full object-cover group-hover:scale-105 transition duration-300 {{ ! $hasStock ? 'grayscale opacity-50' : '' }}"
                                        loading="lazy"
                                    >
                                @else
                                    <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-400 flex items-center justify-center">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                                    </div>
                                @endif
                            </div>

                            <!-- Brand & Product Title -->
                            <div class="space-y-1 mb-3">
                                <span class="text-[10px] font-bold text-slate-400 block uppercase tracking-wider truncate">
                                    {{ $product['brand']['name'] ?? ($product['category']['name'] ?? 'Pantry') }}
                                </span>
                                <h3 class="text-xs sm:text-sm font-bold text-slate-900 line-clamp-2 leading-snug group-hover:text-amber-600 transition">
                                    {{ $product['name'] }}
                                </h3>
                                @if($primaryVariant)
                                    <span class="text-[11px] text-slate-500 font-medium block">
                                        {{ $primaryVariant['name'] }}
                                    </span>
                                @endif
                            </div>
                        </a>

                        <!-- Price & Add Button -->
                        <div class="pt-2 border-t border-slate-100 flex items-center justify-between gap-2 mt-auto">
                            <div>
                                <span class="text-xs sm:text-sm font-extrabold text-slate-950 block font-mono">
                                    Rs {{ number_format($product['primary_price']) }}
                                </span>
                                @if($isSale && $regPrice > $currPrice)
                                    <span class="text-[10px] text-slate-400 line-through font-mono">
                                        Rs {{ number_format($regPrice) }}
                                    </span>
                                @endif
                            </div>

                            @if($hasStock && $primaryVariant)
                                @if($inCartQty > 0)
                                    <div class="flex items-center gap-1.5 bg-slate-950 text-white rounded-xl px-2 py-1 text-xs font-bold shadow-2xs">
                                        <form action="{{ route('storefront.cart.update') }}" method="POST" class="inline">
                                            @csrf
                                            <input type="hidden" name="variant_id" value="{{ $primaryVariant['id'] }}">
                                            <input type="hidden" name="quantity" value="{{ $inCartQty - 1 }}">
                                            <button type="submit" class="px-1 text-amber-400 hover:text-white font-black cursor-pointer">-</button>
                                        </form>
                                        <span class="font-mono text-center min-w-4 text-xs">{{ $inCartQty }}</span>
                                        <form action="{{ route('storefront.cart.update') }}" method="POST" class="inline">
                                            @csrf
                                            <input type="hidden" name="variant_id" value="{{ $primaryVariant['id'] }}">
                                            <input type="hidden" name="quantity" value="{{ $inCartQty + 1 }}">
                                            <button type="submit" class="px-1 text-amber-400 hover:text-white font-black cursor-pointer">+</button>
                                        </form>
                                    </div>
                                @else
                                    <button 
                                        type="button" 
                                        onclick="addToCart({{ $primaryVariant['id'] }}, {{ $currentCity->id }}, this)"
                                        class="px-3.5 py-1.5 rounded-xl bg-slate-950 hover:bg-black text-white text-xs font-bold transition duration-150 shadow-2xs cursor-pointer active:scale-95 flex items-center gap-1"
                                    >
                                        <span>+</span>
                                        <span>ADD</span>
                                    </button>
                                @endif
                            @else
                                <button 
                                    disabled 
                                    class="px-2.5 py-1.5 rounded-xl bg-slate-100 text-slate-400 text-[10px] font-bold cursor-not-allowed border border-slate-200"
                                >
                                    Out
                                </button>
                            @endif
                        </div>

                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Why DarkStore Dharan? Quick Commerce Value Proposition -->
    <div class="bg-white rounded-3xl p-6 sm:p-10 border border-slate-200 shadow-xs space-y-6 mt-12">
        <div class="text-center max-w-xl mx-auto space-y-2">
            <h3 class="text-lg sm:text-2xl font-extrabold text-slate-950">Why Shop From DarkStore {{ $currentCity->name }}?</h3>
            <p class="text-xs text-slate-500">Engineered for ultra-fast local grocery fulfillment in {{ $currentCity->name }} & eastern hubs.</p>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 sm:gap-6 pt-2">
            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-2">
                <div class="w-8 h-8 rounded-xl bg-amber-500 text-white flex items-center justify-center font-bold">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                </div>
                <h4 class="font-bold text-xs text-slate-950">15–30 Min SLA</h4>
                <p class="text-[11px] text-slate-500 leading-relaxed">Micro-warehouses positioned right inside your ward for rapid dispatch.</p>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-2">
                <div class="w-8 h-8 rounded-xl bg-emerald-500 text-white flex items-center justify-center font-bold">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                </div>
                <h4 class="font-bold text-xs text-slate-950">100% Cold-Chain Fresh</h4>
                <p class="text-[11px] text-slate-500 leading-relaxed">Dairy, cold drinks & perishables stored in strict climate-controlled bays.</p>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-2">
                <div class="w-8 h-8 rounded-xl bg-blue-500 text-white flex items-center justify-center font-bold">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                </div>
                <h4 class="font-bold text-xs text-slate-950">Zero Overselling</h4>
                <p class="text-[11px] text-slate-500 leading-relaxed">Atomic pessimistic row locking reserves stock the moment you order.</p>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-2">
                <div class="w-8 h-8 rounded-xl bg-purple-500 text-white flex items-center justify-center font-bold">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                </div>
                <h4 class="font-bold text-xs text-slate-950">Doorstep Return Policy</h4>
                <p class="text-[11px] text-slate-500 leading-relaxed">24h instant replacement or full refund for damaged items.</p>
            </div>
        </div>
    </div>
@endif

</div>
@endsection
