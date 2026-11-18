@extends('layouts.storefront')

@section('content')
<div class="space-y-6 sm:space-y-8">

@if(! $currentCity)
    <!-- City Selection Welcome Screen -->
    <div class="space-y-10 py-6 sm:py-12">

        <!-- Welcome Hero -->
        <div class="text-center max-w-2xl mx-auto space-y-3">
            <h1 class="text-3xl sm:text-5xl font-extrabold text-slate-950 tracking-tight leading-tight">
                Where should we deliver today?
            </h1>
            <p class="text-sm text-slate-500 leading-relaxed max-w-xl mx-auto">
                Groceries, cold drinks and daily essentials in <strong class="text-slate-700">15–30 minutes</strong>. Choose your city to see local stock and prices.
            </p>
        </div>

        <!-- City Selection Cards Grid -->
        <div class="max-w-5xl mx-auto space-y-4">
            <h2 class="text-base sm:text-lg font-bold text-slate-950">Select Your Delivery City</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($cities as $city)
                    <form action="{{ route('storefront.cart.change_city') }}" method="POST" class="h-full">
                        @csrf
                        <input type="hidden" name="city_id" value="{{ $city->id }}">
                        <button
                            type="submit"
                            class="w-full h-full p-5 rounded-2xl bg-white border border-slate-200 hover:border-slate-900 transition text-left cursor-pointer group space-y-4"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-950">{{ $city->name }}</h3>
                                    <p class="text-xs text-slate-500">{{ $city->province ?? 'Nepal' }}</p>
                                </div>
                                <span class="text-xs font-semibold text-slate-700 whitespace-nowrap">~{{ $city->estimated_delivery_minutes }}m SLA</span>
                            </div>
                            <div class="space-y-1 text-xs text-slate-500">
                                <div class="flex justify-between">
                                    <span>Delivery</span>
                                    <span class="text-slate-900">Rs {{ number_format($city->default_delivery_fee) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Free delivery above</span>
                                    <span class="text-slate-900">Rs {{ number_format($city->free_delivery_minimum ?? 500) }}</span>
                                </div>
                            </div>
                            <div class="flex items-center justify-between text-sm font-semibold text-slate-950">
                                <span>Shop in {{ $city->name }}</span>
                                <span class="group-hover:translate-x-1 transition">→</span>
                            </div>
                        </button>
                    </form>
                @endforeach
            </div>
        </div>

        @include('storefront.partials.value-props')
    </div>
@else
    <!-- Dynamic Banners or Fallback Hero -->
    @if(!empty($banners))
        <div class="flex gap-4 overflow-x-auto snap-x snap-mandatory scrollbar-none pb-2 -mx-4 px-4 sm:mx-0 sm:px-0">
            @foreach($banners as $banner)
                {{-- Narrower slides let the next banner peek in; a lone banner fills the row. --}}
                <div class="snap-center shrink-0 w-full {{ count($banners) > 1 ? 'sm:w-[85%] md:w-3/4' : '' }} rounded-2xl overflow-hidden relative">
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
        <!-- Hero Fallback -->
        <div class="py-2 sm:py-4 space-y-2">
            <h1 class="text-2xl sm:text-4xl font-extrabold text-slate-950 tracking-tight leading-tight">
                Daily Essentials at your Door in {{ $currentCity->name }}
            </h1>
            <p class="text-sm text-slate-500">
                Delivered in ~{{ $currentCity->estimated_delivery_minutes ?? 30 }} minutes · Free delivery above Rs {{ number_format($currentCity->free_delivery_minimum ?? 500) }} · Cash on delivery or wallet
            </p>
        </div>
    @endif

    <!-- Category Filter Chips Strip -->
    <div class="space-y-2">
        <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none">
            @if(!empty($featuredProducts))
                <a
                    href="{{ route('storefront.home', ['city_id' => $currentCity->id]) }}"
                    class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap transition cursor-pointer {{ !$selectedCategoryId && !request('q') ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200' }}"
                >
                    Featured
                </a>
            @endif
            @foreach($topCategories as $category)
                <a
                    href="{{ route('storefront.home', array_filter(['category_id' => $category->id, 'city_id' => $currentCity->id, 'q' => request('q')])) }}"
                    class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap transition cursor-pointer {{ $selectedCategoryId == $category->id ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200' }}"
                >
                    {{ $category->name }} <span class="opacity-60">{{ $category->products_count }}</span>
                </a>
            @endforeach
        </div>

        @if($subCategories->isNotEmpty())
            <div class="flex items-center gap-4 overflow-x-auto pb-1 scrollbar-none text-sm">
                @foreach($subCategories as $subCategory)
                    <a
                        href="{{ route('storefront.home', array_filter(['category_id' => $subCategory->id, 'city_id' => $currentCity->id, 'q' => request('q')])) }}"
                        class="py-1 whitespace-nowrap transition cursor-pointer border-b-2 {{ $selectedCategoryId == $subCategory->id ? 'border-slate-900 text-slate-950 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-900' }}"
                    >
                        {{ $subCategory->name }} <span class="opacity-60">{{ $subCategory->products_count }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Product Grid Section -->
    <div>
        <div class="flex items-baseline justify-between gap-3 mb-4">
            <h2 class="text-lg sm:text-xl font-bold text-slate-950 tracking-tight">
                @if(request('q'))
                    Search results for "{{ request('q') }}"
                @elseif($selectedCategoryId)
                    Category Products
                @else
                    Featured in {{ $currentCity->name }}
                @endif
            </h2>
            <span class="text-xs text-slate-500 whitespace-nowrap">{{ count($products) }} items</span>
        </div>

        @if(empty($products))
            <div class="py-16 text-center space-y-3 bg-white rounded-2xl border border-slate-200 p-8">
                <h3 class="text-base font-bold text-slate-900">No products available in this view</h3>
                <p class="text-sm text-slate-500 max-w-sm mx-auto">
                    Items are localized to each city's store. Try another category or clear your filters.
                </p>
                <button
                    onclick="document.getElementById('city-modal').showModal()"
                    class="mt-2 px-5 py-2.5 bg-slate-900 hover:bg-black text-white font-semibold rounded-xl text-sm transition cursor-pointer"
                >
                    Change Delivery City
                </button>
            </div>
        @else
            <!-- Responsive Grid: 2 columns on Mobile, 3 on Tablet, 4-5 on Desktop -->
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 sm:gap-4">
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
                    <div class="bg-white rounded-2xl p-3 border border-slate-200 hover:border-slate-300 transition flex flex-col group">

                        <!-- Product Thumbnail & Links -->
                        <a href="{{ route('storefront.product', $product['slug']) }}" class="block cursor-pointer">
                            <div class="aspect-square bg-slate-50 rounded-xl overflow-hidden mb-3 flex items-center justify-center relative">
                                @if(!empty($product['image']))
                                    <img
                                        src="{{ $product['image'] }}"
                                        alt="{{ $product['name'] }}"
                                        class="w-full h-full object-cover {{ ! $hasStock ? 'grayscale opacity-50' : '' }}"
                                        loading="lazy"
                                    >
                                @else
                                    <svg class="w-8 h-8 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                                @endif

                                {{-- One status badge at most: stock state wins over discount --}}
                                @if(! $hasStock)
                                    <span class="absolute top-2 left-2 bg-white/95 text-slate-700 px-2 py-0.5 rounded-md text-[10px] font-semibold">Sold out</span>
                                @elseif($stockAvailable > 0 && $stockAvailable <= 5)
                                    <span class="absolute top-2 left-2 bg-white/95 text-amber-700 px-2 py-0.5 rounded-md text-[10px] font-semibold">Only {{ $stockAvailable }} left</span>
                                @elseif($discountPct > 0)
                                    <span class="absolute top-2 left-2 bg-emerald-600 text-white px-2 py-0.5 rounded-md text-[10px] font-semibold">{{ $discountPct }}% off</span>
                                @endif
                            </div>

                            <!-- Product Title -->
                            <div class="space-y-0.5 mb-3">
                                <h3 class="text-sm font-semibold text-slate-900 line-clamp-2 leading-snug">
                                    {{ $product['name'] }}
                                </h3>
                                @if($primaryVariant)
                                    <span class="text-xs text-slate-500 block">
                                        {{ $primaryVariant['name'] }}
                                    </span>
                                @endif
                            </div>
                        </a>

                        <!-- Price & Add Button -->
                        <div class="flex items-center justify-between gap-2 mt-auto">
                            <div class="leading-tight">
                                <span class="text-sm font-bold text-slate-950 block">
                                    Rs {{ number_format($product['primary_price']) }}
                                </span>
                                @if($isSale && $regPrice > $currPrice)
                                    <span class="text-[11px] text-slate-400 line-through">
                                        Rs {{ number_format($regPrice) }}
                                    </span>
                                @endif
                            </div>

                            @if($hasStock && $primaryVariant)
                                @if($inCartQty > 0)
                                    <div class="flex items-center gap-1.5 bg-slate-900 text-white rounded-xl px-2 py-1 text-sm font-semibold">
                                        <form action="{{ route('storefront.cart.update') }}" method="POST" class="inline">
                                            @csrf
                                            <input type="hidden" name="variant_id" value="{{ $primaryVariant['id'] }}">
                                            <input type="hidden" name="quantity" value="{{ $inCartQty - 1 }}">
                                            <button type="submit" class="px-1 cursor-pointer">-</button>
                                        </form>
                                        <span class="text-center min-w-4 text-xs">{{ $inCartQty }}</span>
                                        <form action="{{ route('storefront.cart.update') }}" method="POST" class="inline">
                                            @csrf
                                            <input type="hidden" name="variant_id" value="{{ $primaryVariant['id'] }}">
                                            <input type="hidden" name="quantity" value="{{ $inCartQty + 1 }}">
                                            <button type="submit" class="px-1 cursor-pointer">+</button>
                                        </form>
                                    </div>
                                @else
                                    <button
                                        type="button"
                                        onclick="addToCart({{ $primaryVariant['id'] }}, {{ $currentCity->id }}, this)"
                                        class="px-4 py-1.5 rounded-xl bg-slate-900 hover:bg-black text-white text-xs font-semibold transition cursor-pointer active:scale-95"
                                    >
                                        Add
                                    </button>
                                @endif
                            @else
                                <button
                                    disabled
                                    class="px-3 py-1.5 rounded-xl bg-slate-100 text-slate-400 text-xs font-semibold cursor-not-allowed"
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

    @include('storefront.partials.value-props', ['cityName' => $currentCity->name, 'wrapperClass' => 'mt-16'])
@endif

</div>
@endsection
