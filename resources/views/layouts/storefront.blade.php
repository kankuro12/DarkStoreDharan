<!DOCTYPE html>
<html lang="en" class="h-full bg-[#F8FAFC] text-[#0F172A] antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? ($storeSettings['default_meta_title'] ?? 'DarkStore Dharan - Fast Local Delivery in 30 Minutes') }}</title>
    <meta name="description" content="{{ $metaDescription ?? ($storeSettings['default_meta_description'] ?? 'DarkStore delivers groceries and daily essentials in 15-30 minutes directly from neighborhood micro-warehouses.') }}">
    
    <meta property="og:title" content="{{ $title ?? ($storeSettings['default_meta_title'] ?? 'DarkStore Dharan') }}">
    <meta property="og:description" content="{{ $metaDescription ?? ($storeSettings['default_meta_description'] ?? 'Fast local delivery.') }}">
    <meta property="og:url" content="{{ request()->url() }}">
    <meta property="og:type" content="{{ $metaType ?? 'website' }}">
    @if(isset($metaImage) || isset($storeSettings['default_meta_image']))
        <meta property="og:image" content="{{ isset($metaImage) ? $metaImage : asset('storage/' . $storeSettings['default_meta_image']) }}">
        <meta name="twitter:image" content="{{ isset($metaImage) ? $metaImage : asset('storage/' . $storeSettings['default_meta_image']) }}">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title ?? ($storeSettings['default_meta_title'] ?? 'DarkStore Dharan') }}">
    <meta name="twitter:description" content="{{ $metaDescription ?? ($storeSettings['default_meta_description'] ?? 'Fast local delivery.') }}">

    @if(isset($storeSettings['store_favicon']))
        <link rel="icon" href="{{ asset('storage/' . $storeSettings['store_favicon']) }}">
    @endif
    
    <!-- Modern Typography: Inter & Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    @vite(['resources/css/app.css'])

    <style>
        body { font-family: 'Plus Jakarta Sans', 'Inter', sans-serif; }
        .glass-pill {
            background: rgba(15, 23, 42, 0.92);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
        dialog::backdrop {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
        }
    </style>
</head>
<body class="flex min-h-full flex-col font-sans pb-24 md:pb-12 text-slate-900 bg-slate-50">

    <!-- Top Announcement Bar -->
    <div class="bg-slate-950 text-white text-xs py-2 px-4 border-b border-slate-800">
        <div class="max-w-7xl mx-auto flex items-center justify-between gap-3 text-xs">
            <div class="flex items-center gap-2 overflow-hidden truncate">
                <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-amber-400 text-slate-950 shrink-0">
                    <svg class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="currentColor"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                </span>
                @if($currentCity)
                    <span class="truncate">
                        <strong>Dark Store Express:</strong> 15–30 min delivery in <strong>{{ $currentCity->name }}</strong>
                    </span>
                    <span class="hidden md:inline text-slate-500">•</span>
                    <span class="hidden md:inline text-amber-300">
                        Free shipping on orders above Rs {{ number_format($currentCity->free_delivery_minimum ?? 500) }}
                    </span>
                @else
                    <span class="truncate">
                        <strong>Dark Store Express:</strong> Fast 15–30 min delivery in Nepal • <strong class="text-amber-300">Select your delivery city to start</strong>
                    </span>
                @endif
            </div>

            <div class="hidden sm:flex items-center gap-4 text-[11px] text-slate-400">
                <span class="inline-flex items-center gap-1.5 text-emerald-400 font-medium">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    Dark Store Hubs Online
                </span>
                @auth
                    <a href="{{ route('storefront.account.orders') }}" class="hover:text-white transition font-medium flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        {{ auth()->user()->name }}
                    </a>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="hover:text-amber-300 transition font-medium cursor-pointer">Logout</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="hover:text-white transition font-medium flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" /></svg>
                        Login / Sign Up
                    </a>
                @endauth
            </div>
        </div>
    </div>

    <!-- Main Navigation Header -->
    <header class="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-slate-200/80 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 gap-3 md:gap-8">
                
                <!-- Brand Wordmark & Location -->
                <div class="flex items-center gap-3 sm:gap-6 shrink-0">
                    <a href="{{ route('storefront.home') }}" class="flex items-center gap-2 group cursor-pointer">
                        @if(!empty($storeSettings['store_icon']))
                            <div class="w-9 h-9 rounded-xl overflow-hidden shadow-xs group-hover:scale-105 transition duration-200">
                                <img src="{{ asset('storage/' . $storeSettings['store_icon']) }}" alt="Icon" class="w-full h-full object-cover">
                            </div>
                        @else
                            <div class="w-9 h-9 rounded-xl bg-slate-950 text-amber-400 flex items-center justify-center font-black shadow-xs group-hover:scale-105 transition duration-200">
                                <svg class="w-5 h-5 fill-amber-400" viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                            </div>
                        @endif
                        <div>
                            <span class="font-extrabold text-lg sm:text-xl tracking-tight text-slate-950 block leading-none">
                                {{ $storeSettings['store_name'] ?? 'darkstore.np' }}
                            </span>
                            <span class="text-[9px] font-bold text-slate-600 tracking-wider uppercase block">Instant Fulfillment</span>
                        </div>
                    </a>

                    <!-- Persistent City Selector Pill -->
                    <button 
                        type="button" 
                        onclick="document.getElementById('city-modal').showModal()"
                        class="hidden sm:flex items-center gap-2 px-3.5 py-1.5 rounded-full {{ $currentCity ? 'bg-slate-100 hover:bg-slate-200 text-slate-700' : 'bg-amber-100 hover:bg-amber-200 text-amber-950 ring-2 ring-amber-400/60' }} text-xs font-semibold border border-slate-200 transition cursor-pointer"
                        title="{{ $currentCity ? 'Change Delivery City' : 'Select Delivery City' }}"
                    >
                        <svg class="w-3.5 h-3.5 text-rose-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        @if($currentCity)
                            <span class="text-slate-500">In</span>
                            <span class="text-slate-950 font-bold underline decoration-amber-500 decoration-2 underline-offset-2">
                                {{ $currentCity->name }}
                            </span>
                        @else
                            <span class="font-extrabold text-amber-900 flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-ping"></span>
                                Select City
                            </span>
                        @endif
                        <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                </div>

                <!-- Search Input Bar with Live Autocomplete -->
                <div class="flex-1 max-w-lg hidden md:block relative">
                    <form action="{{ route('storefront.home') }}" method="GET" class="relative">
                        @if(request('category_id'))
                            <input type="hidden" name="category_id" value="{{ request('category_id') }}">
                        @endif
                        <input 
                            type="text" 
                            id="live-search-input"
                            name="q" 
                            autocomplete="off"
                            value="{{ request('q') }}"
                            placeholder="Search dairy, chilled drinks, noodles, snacks..." 
                            class="w-full bg-slate-100/80 hover:bg-slate-100 focus:bg-white border border-slate-200 focus:border-slate-900 rounded-full py-2.5 pl-10 pr-10 text-xs font-medium focus:outline-none transition shadow-2xs"
                        >
                        <span class="absolute left-3.5 top-3 text-slate-400">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <span class="absolute right-3 top-2.5 text-[10px] font-mono text-slate-600 bg-slate-200 px-1.5 py-0.5 rounded border border-slate-300 pointer-events-none">/</span>
                    </form>

                    <!-- Suggestions Dropdown List -->
                    <ul id="search-autocomplete" class="hidden absolute left-0 right-0 top-full mt-2 bg-white rounded-2xl shadow-xl border border-slate-200 z-50 divide-y divide-slate-100 max-h-80 overflow-y-auto">
                    </ul>
                </div>

                <!-- Header Actions (Cart Drawer Button & City on Mobile) -->
                <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                    <!-- Mobile City Selector Button -->
                    <button 
                        type="button" 
                        onclick="document.getElementById('city-modal').showModal()"
                        class="sm:hidden flex items-center gap-1.5 text-xs font-bold {{ $currentCity ? 'bg-slate-100 text-slate-900' : 'bg-amber-100 text-amber-950 ring-2 ring-amber-400/60' }} px-3 py-1.5 rounded-full border border-slate-200 transition cursor-pointer"
                    >
                        <svg class="w-3.5 h-3.5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        </svg>
                        <span>{{ $currentCity?->name ?? 'Select City' }}</span>
                        <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <!-- Cart Drawer Trigger Button -->
                    @php
                        $cartCount = $cartItemCount ?? 0;
                        $cartTotal = $cartDetailed['grand_total'] ?? 0;
                    @endphp
                    <button 
                        type="button" 
                        onclick="document.getElementById('cart-drawer').showModal()"
                        class="relative flex items-center gap-2.5 bg-slate-950 text-white px-4 py-2 rounded-full font-bold text-xs hover:bg-black transition duration-200 cursor-pointer shadow-xs active:scale-95"
                    >
                        <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                        <span class="hidden sm:inline">Bag</span>
                        <span id="cart-badge-count" class="bg-amber-400 text-slate-950 px-1.5 py-0.2 rounded-full text-[10px] font-black min-w-4 text-center">
                            {{ $cartCount }}
                        </span>
                        <span id="cart-badge-total" class="hidden lg:inline text-slate-300 font-semibold text-[11px] border-l border-slate-700 pl-2">
                            Rs {{ number_format($cartTotal) }}
                        </span>
                    </button>
                </div>

            </div>

            <!-- Mobile Search Bar (Visible on mobile screens) -->
            <div class="pb-3 md:hidden">
                <form action="{{ route('storefront.home') }}" method="GET" class="relative">
                    @if(request('category_id'))
                        <input type="hidden" name="category_id" value="{{ request('category_id') }}">
                    @endif
                    <input 
                        type="text" 
                        name="q" 
                        value="{{ request('q') }}"
                        placeholder="Search dairy, beverages, snacks..." 
                        class="w-full bg-slate-100 focus:bg-white border border-slate-200 focus:border-slate-900 rounded-full py-2 pl-9 pr-4 text-xs font-medium focus:outline-none transition"
                    >
                    <span class="absolute left-3 top-2.5 text-slate-400">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </span>
                </form>
            </div>
        </div>
    </header>

    <!-- Notification & Flash Alerts -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-3 w-full">
        @if(session('cart_warning'))
            <div class="bg-amber-50 border-l-4 border-amber-500 p-4 rounded-r-xl mb-3 text-xs text-amber-900 shadow-xs flex items-start gap-3">
                <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div class="flex-1">
                    <strong class="font-bold block mb-0.5 text-amber-950">Cart Adjusted for Selected City:</strong>
                    {!! session('cart_warning') !!}
                </div>
            </div>
        @endif

        @if(session('success'))
            <div class="bg-emerald-50 border-l-4 border-emerald-500 p-3.5 rounded-r-xl mb-3 text-xs text-emerald-950 font-semibold shadow-xs flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="bg-rose-50 border-l-4 border-rose-500 p-3.5 rounded-r-xl mb-3 text-xs text-rose-950 font-semibold shadow-xs flex items-center gap-2">
                <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @if(session('info'))
            <div class="bg-blue-50 border-l-4 border-blue-500 p-3.5 rounded-r-xl mb-3 text-xs text-blue-950 font-semibold shadow-xs flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('info') }}</span>
            </div>
        @endif
    </div>

    <!-- Main Page Content -->
    <main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full py-4">
        @yield('content')
    </main>

    <!-- Floating Sticky Cart Banner -->
    <div id="sticky-cart-bar" class="{{ $cartCount > 0 ? 'flex' : 'hidden' }} fixed bottom-18 md:bottom-6 left-4 right-4 md:left-auto md:right-8 md:max-w-md z-40 bg-slate-950 text-white rounded-2xl p-3 sm:p-4 shadow-2xl border border-slate-800 items-center justify-between gap-3 transition-all duration-300">
        <div class="flex items-center gap-3 min-w-0">
            <div class="w-10 h-10 rounded-xl bg-amber-400 text-slate-950 flex items-center justify-center font-black shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
            </div>
            <div class="min-w-0">
                <div class="flex items-baseline gap-2">
                    <span id="floating-cart-count" class="font-extrabold text-sm text-white">{{ $cartCount }} items</span>
                    <span class="text-xs text-slate-400">•</span>
                    <span id="floating-cart-total" class="font-black text-sm text-amber-400">Rs {{ number_format($cartTotal) }}</span>
                </div>
                <p class="text-[11px] text-slate-300 truncate">
                    <strong>{{ $currentCity?->estimated_delivery_minutes ?? 30 }}m</strong> dispatch from {{ $currentCity?->name ?? 'Dark Store' }}
                </p>
            </div>
        </div>

        <button 
            type="button" 
            onclick="document.getElementById('cart-drawer').showModal()"
            class="px-4 py-2 bg-amber-400 hover:bg-amber-300 text-slate-950 font-black text-xs rounded-xl uppercase tracking-wider transition shrink-0 cursor-pointer shadow-xs active:scale-95"
        >
            View Bag
        </button>
    </div>

    <!-- Storefront Footer -->
    <footer class="bg-white border-t border-slate-200 mt-16 pt-12 pb-24 md:pb-12 text-slate-600 text-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
            
            <!-- Value Props Grid -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 pb-8 border-b border-slate-100">
                <div class="space-y-1">
                    <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center mb-2 font-bold">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                    </div>
                    <h4 class="font-bold text-slate-900 text-sm">30-Min Fast Dispatch</h4>
                    <p class="text-[11px] text-slate-500">Local hyper-speed dark stores strategically placed in your neighborhood.</p>
                </div>
                <div class="space-y-1">
                    <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center mb-2 font-bold">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                    </div>
                    <h4 class="font-bold text-slate-900 text-sm">Cold-Chain Guaranteed</h4>
                    <p class="text-[11px] text-slate-500">Dairy, beverages, and fresh goods maintained at strict temperature standards.</p>
                </div>
                <div class="space-y-1">
                    <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center mb-2 font-bold">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                    </div>
                    <h4 class="font-bold text-slate-900 text-sm">24h Replacement / Return</h4>
                    <p class="text-[11px] text-slate-500">Damaged or incorrect item? Instant support resolution with zero hassle.</p>
                </div>
                <div class="space-y-1">
                    <div class="w-8 h-8 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center mb-2 font-bold">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                    </div>
                    <h4 class="font-bold text-slate-900 text-sm">Multi-Channel Payments</h4>
                    <p class="text-[11px] text-slate-500">Pay safely via Cash on Delivery, eSewa, Khalti, or mobile banking QR.</p>
                </div>
            </div>

            <!-- Footer Columns -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                <div class="space-y-3">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-lg bg-slate-950 text-amber-400 flex items-center justify-center font-black">
                            <svg class="w-3.5 h-3.5 fill-amber-400" viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                        </div>
                        <span class="font-black text-sm text-slate-950">DarkStore Nepal</span>
                    </div>
                    <p class="text-[11px] text-slate-500 leading-relaxed">
                        Next-generation quick-commerce architecture bringing instant 30-minute retail delivery to Dharan, Itahari, and eastern hubs.
                    </p>
                </div>

                <div class="space-y-2">
                    <h5 class="font-bold text-slate-900 uppercase tracking-wider text-[11px]">Operating Hubs</h5>
                    <ul class="space-y-1.5 text-[11px]">
                        @foreach($allCities as $c)
                            <li>
                                <form action="{{ route('storefront.cart.change_city') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="city_id" value="{{ $c->id }}">
                                    <button type="submit" class="hover:text-slate-950 transition cursor-pointer flex items-center gap-1.5">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $currentCity?->id === $c->id ? 'bg-amber-500' : 'bg-slate-300' }}"></span>
                                        <span>{{ $c->name }}</span>
                                        <span class="text-slate-600 font-mono">({{ $c->estimated_delivery_minutes }}m)</span>
                                    </button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="space-y-2">
                    <h5 class="font-bold text-slate-900 uppercase tracking-wider text-[11px]">Operations & Legal</h5>
                    <ul class="space-y-1.5 text-[11px]">
                        <li><a href="{{ route('storefront.contact') }}" class="hover:text-slate-900 transition">Contact Us</a></li>
                        <li><a href="{{ route('storefront.privacy') }}" class="hover:text-slate-900 transition">Privacy & Data Policy</a></li>
                        <li><a href="{{ route('storefront.terms') }}" class="hover:text-slate-900 transition">Terms of Service</a></li>
                        @auth
                            <li><a href="{{ route('storefront.account.orders') }}" class="hover:text-slate-900 transition">Order History</a></li>
                            <li><a href="{{ route('storefront.account.addresses') }}" class="hover:text-slate-900 transition">Saved Addresses</a></li>
                        @else
                            <li><a href="{{ route('login') }}" class="hover:text-slate-900 transition">Customer Login</a></li>
                        @endauth
                    </ul>
                </div>

                <div class="space-y-2">
                    <h5 class="font-bold text-slate-900 uppercase tracking-wider text-[11px]">Payment Methods</h5>
                    <div class="flex flex-wrap gap-2 text-[10px] font-bold">
                        <span class="px-2.5 py-1 rounded bg-slate-100 border border-slate-200 text-slate-700">Cash on Delivery</span>
                        <span class="px-2.5 py-1 rounded bg-emerald-50 border border-emerald-200 text-emerald-800">eSewa Wallet</span>
                        <span class="px-2.5 py-1 rounded bg-purple-50 border border-purple-200 text-purple-800">Khalti Pay</span>
                        <span class="px-2.5 py-1 rounded bg-rose-50 border border-rose-200 text-rose-800">Fonepay QR</span>
                    </div>
                </div>
            </div>

            <!-- Copyright -->
            <div class="pt-6 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4 text-[11px] text-slate-600">
                <p>© {{ date('Y') }} DarkStore Dharan Technologies Pvt. Ltd. All rights reserved.</p>
                <p class="font-mono text-[10px]">Built for Ultra-Fast Urban Micro-Fulfillment</p>
            </div>
        </div>
    </footer>

    <!-- Native HTML5 Dialog: City Selector Modal -->
    <dialog id="city-modal" class="rounded-3xl p-0 w-full max-w-md shadow-2xl backdrop:bg-slate-950/60 border-0 m-auto">
        <div class="p-6 bg-white space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div>
                    <h3 class="font-bold text-base text-slate-900">
                        {{ $currentCity ? 'Change your delivery city' : 'Select your delivery city' }}
                    </h3>
                    <p class="text-xs text-slate-500">Live inventory and delivery SLA are localized to your dark store</p>
                </div>
                <button type="button" onclick="document.getElementById('city-modal').close()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-900 flex items-center justify-center font-bold text-sm cursor-pointer transition">✕</button>
            </div>

            <div class="space-y-2.5">
                @foreach($allCities as $city)
                    <form action="{{ route('storefront.cart.change_city') }}" method="POST">
                        @csrf
                        <input type="hidden" name="city_id" value="{{ $city->id }}">
                        <button 
                            type="submit" 
                            class="w-full text-left p-3.5 rounded-2xl border transition duration-150 cursor-pointer flex items-center justify-between {{ $currentCity?->id === $city->id ? 'border-amber-500 bg-amber-50/60 ring-2 ring-amber-500/20' : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50' }}"
                        >
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-xl {{ $currentCity?->id === $city->id ? 'bg-amber-500 text-white' : 'bg-slate-100 text-slate-600' }} flex items-center justify-center font-bold">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /></svg>
                                </div>
                                <div>
                                    <span class="font-bold text-sm block text-slate-900">{{ $city->name }}</span>
                                    <span class="text-[11px] text-slate-500">{{ $city->province }} • Standard delivery Rs {{ number_format($city->default_delivery_fee) }}</span>
                                </div>
                            </div>
                            <span class="text-xs font-bold text-emerald-800 bg-emerald-100 px-2.5 py-1 rounded-full">
                                {{ $city->estimated_delivery_minutes }} min delivery
                            </span>
                        </button>
                    </form>
                @endforeach
            </div>
        </div>
    </dialog>

    <!-- Native HTML5 Dialog: Cart Drawer Modal -->
    <dialog id="cart-drawer" class="rounded-3xl p-0 w-full max-w-lg shadow-2xl backdrop:bg-slate-950/60 border-0 m-auto">
        <div class="p-6 bg-white space-y-4 flex flex-col max-h-[88vh]">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-slate-950 text-amber-400 flex items-center justify-center font-bold">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-base text-slate-900">Your Basket</h3>
                        <p class="text-xs text-slate-500">
                            @if($currentCity)
                                Delivering from {{ $currentCity->name }} Dark Store
                            @else
                                Please choose your delivery city first
                            @endif
                        </p>
                    </div>
                </div>
                <button type="button" onclick="document.getElementById('cart-drawer').close()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-900 flex items-center justify-center font-bold text-sm cursor-pointer transition">✕</button>
            </div>

            @if(empty($cartDetailed['items']))
                <div class="py-14 text-center space-y-3">
                    <div class="w-16 h-16 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                    </div>
                    <p class="text-base font-bold text-slate-800">Your basket is empty</p>
                    <p class="text-xs text-slate-500 max-w-xs mx-auto">Add grocery essentials, cold drinks, or snacks for instant 30-minute delivery.</p>
                </div>
            @else
                <!-- Free delivery indicator & progress bar -->
                @php
                    $minFree = (float) ($currentCity?->free_delivery_minimum ?? 500);
                    $subtotal = $cartDetailed['subtotal'];
                    $toFree = max(0, $minFree - $subtotal);
                    $progressPct = min(100, round(($subtotal / max(1, $minFree)) * 100));
                @endphp
                <div class="bg-amber-50/80 border border-amber-200/70 p-3 rounded-2xl space-y-2 text-xs">
                    <div class="flex items-center justify-between font-semibold text-amber-950">
                        @if($toFree > 0)
                            <span>Add <strong>Rs {{ number_format($toFree) }}</strong> more for <strong>FREE DELIVERY</strong>!</span>
                            <span class="font-mono text-[11px]">{{ $progressPct }}%</span>
                        @else
                            <span class="text-emerald-700 font-bold flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                Qualified for FREE Express Delivery!
                            </span>
                        @endif
                    </div>
                    <div class="w-full bg-amber-200/50 rounded-full h-1.5 overflow-hidden">
                        <div class="bg-amber-500 h-1.5 rounded-full transition-all duration-300" style="width: {{ $progressPct }}%"></div>
                    </div>
                </div>

                <!-- Cart Items List -->
                <div class="flex-1 overflow-y-auto space-y-3 divide-y divide-slate-100 pr-1">
                    @foreach($cartDetailed['items'] as $item)
                        <div class="pt-3 flex items-center justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <h4 class="text-xs font-bold text-slate-900 truncate">{{ $item['product_name'] }}</h4>
                                <span class="text-xs font-semibold text-slate-500">Rs {{ number_format($item['unit_price']) }} each</span>
                            </div>

                            <!-- Quantity Stepper -->
                            <div class="flex items-center gap-2 bg-slate-100 px-2.5 py-1 rounded-xl border border-slate-200">
                                <form action="{{ route('storefront.cart.update') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="variant_id" value="{{ $item['variant_id'] }}">
                                    <input type="hidden" name="quantity" value="{{ $item['quantity'] - 1 }}">
                                    <button type="submit" class="text-xs font-black text-slate-600 hover:text-black px-1 cursor-pointer transition">-</button>
                                </form>
                                <span class="text-xs font-bold w-4 text-center text-slate-900">{{ $item['quantity'] }}</span>
                                <form action="{{ route('storefront.cart.update') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="variant_id" value="{{ $item['variant_id'] }}">
                                    <input type="hidden" name="quantity" value="{{ $item['quantity'] + 1 }}">
                                    <button type="submit" class="text-xs font-black text-slate-600 hover:text-black px-1 cursor-pointer transition">+</button>
                                </form>
                            </div>

                            <span class="text-xs font-bold text-slate-900 w-16 text-right font-mono">
                                Rs {{ number_format($item['total']) }}
                            </span>
                        </div>
                    @endforeach
                </div>

                <!-- Totals & Checkout CTA -->
                <div class="pt-3 border-t border-slate-100 space-y-2 text-xs">
                    <div class="flex justify-between text-slate-600">
                        <span>Items Subtotal</span>
                        <span class="font-semibold text-slate-900 font-mono">Rs {{ number_format($cartDetailed['subtotal']) }}</span>
                    </div>
                    @if($cartDetailed['discount'] > 0)
                        <div class="flex justify-between text-emerald-700 font-semibold">
                            <span>Coupon Savings</span>
                            <span class="font-mono">- Rs {{ number_format($cartDetailed['discount']) }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between text-slate-600">
                        <span>Delivery Fee</span>
                        <span class="font-semibold {{ $cartDetailed['delivery_fee'] == 0 ? 'text-emerald-700' : 'text-slate-900' }} font-mono">
                            {{ $cartDetailed['delivery_fee'] == 0 ? 'FREE' : 'Rs ' . number_format($cartDetailed['delivery_fee']) }}
                        </span>
                    </div>
                    <div class="flex justify-between text-base font-black text-slate-950 pt-2 border-t border-slate-100">
                        <span>Total to Pay</span>
                        <span class="font-mono">Rs {{ number_format($cartDetailed['grand_total']) }}</span>
                    </div>

                    <a 
                        href="{{ route('storefront.checkout.show') }}" 
                        class="block w-full text-center py-3.5 bg-slate-950 hover:bg-black text-white font-extrabold text-xs rounded-2xl uppercase tracking-wider transition shadow-md cursor-pointer mt-2 active:scale-98"
                    >
                        Proceed to Checkout
                    </a>
                </div>
            @endif
        </div>
    </dialog>

    <!-- Mobile Floating Bottom Pill Nav -->
    <nav class="md:hidden fixed bottom-3 left-4 right-4 z-50 glass-pill text-white rounded-full py-2.5 px-6 shadow-2xl flex items-center justify-around border border-slate-800">
        <a href="{{ route('storefront.home') }}" class="flex flex-col items-center gap-0.5 {{ request()->routeIs('storefront.home') ? 'text-amber-400' : 'text-slate-400 hover:text-white' }} cursor-pointer">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
            <span class="text-[9px] font-bold">Home</span>
        </a>
        <button type="button" onclick="document.getElementById('city-modal').showModal()" class="flex flex-col items-center gap-0.5 text-slate-400 hover:text-white cursor-pointer">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /></svg>
            <span class="text-[9px] font-bold">{{ $currentCity?->name ?? 'City' }}</span>
        </button>
        <button type="button" onclick="document.getElementById('cart-drawer').showModal()" class="flex flex-col items-center gap-0.5 text-slate-400 hover:text-white relative cursor-pointer">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
            <span class="text-[9px] font-bold">Bag</span>
            @if($cartCount > 0)
                <span class="absolute -top-1 -right-2 bg-amber-400 text-slate-950 text-[9px] font-black rounded-full px-1.5">
                    {{ $cartCount }}
                </span>
            @endif
        </button>
        @auth
            <a href="{{ route('storefront.account.orders') }}" class="flex flex-col items-center gap-0.5 text-slate-400 hover:text-white cursor-pointer">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                <span class="text-[9px] font-bold">Profile</span>
            </a>
        @else
            <a href="{{ route('login') }}" class="flex flex-col items-center gap-0.5 text-slate-400 hover:text-white cursor-pointer">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" /></svg>
                <span class="text-[9px] font-bold">Login</span>
            </a>
        @endauth
    </nav>

    <!-- Vanilla JS for Micro-Interactions -->
    <script>
        // Optimistic Add to Cart via Fetch
        async function addToCart(variantId, cityId, buttonEl) {
            const originalContent = buttonEl.innerHTML;
            buttonEl.disabled = true;
            buttonEl.innerHTML = '<span class="inline-block animate-spin">⏳</span> Adding...';

            try {
                const response = await fetch('/cart/add', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        variant_id: variantId,
                        city_id: cityId,
                        quantity: 1
                    })
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    buttonEl.innerHTML = 'Added ✓';
                    buttonEl.classList.remove('bg-slate-950', 'hover:bg-black');
                    buttonEl.classList.add('bg-emerald-600', 'text-white');
                    
                    // Fetch the current page HTML silently to update the cart drawer UI in sync (cache busted)
                    const fetchUrl = window.location.href + (window.location.href.includes('?') ? '&' : '?') + '_t=' + new Date().getTime();
                    fetch(fetchUrl, { headers: { 'Cache-Control': 'no-cache' } })
                        .then(res => res.text())
                        .then(html => {
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(html, 'text/html');
                            
                            // Swap Cart Drawer Content
                            const newDrawer = doc.getElementById('cart-drawer');
                            const oldDrawer = document.getElementById('cart-drawer');
                            if (newDrawer && oldDrawer) oldDrawer.innerHTML = newDrawer.innerHTML;

                            // Swap Header Badge
                            const newBadge = doc.getElementById('cart-badge-count');
                            const oldBadge = document.getElementById('cart-badge-count');
                            if (newBadge && oldBadge) oldBadge.innerHTML = newBadge.innerHTML;
                            
                            const newBadgeTotal = doc.getElementById('cart-badge-total');
                            const oldBadgeTotal = document.getElementById('cart-badge-total');
                            if (newBadgeTotal && oldBadgeTotal) oldBadgeTotal.innerHTML = newBadgeTotal.innerHTML;

                            // Swap Floating Banner
                            const floatBar = document.getElementById('sticky-cart-bar');
                            const newFloatCount = doc.getElementById('floating-cart-count');
                            const oldFloatCount = document.getElementById('floating-cart-count');
                            if (newFloatCount && oldFloatCount) oldFloatCount.innerHTML = newFloatCount.innerHTML;

                            const newFloatTotal = doc.getElementById('floating-cart-total');
                            const oldFloatTotal = document.getElementById('floating-cart-total');
                            if (newFloatTotal && oldFloatTotal) oldFloatTotal.innerHTML = newFloatTotal.innerHTML;

                            if (floatBar) {
                                floatBar.classList.remove('hidden');
                                floatBar.classList.add('flex');
                            }

                            // Restore button visually
                            setTimeout(() => {
                                // Rather than resetting to ADD, if we are on the homepage we can swap the entire product card action row to the stepper, but reloading the page is cleaner if they need the stepper immediately. 
                                // For now, just reset the button so it can be clicked again.
                                buttonEl.innerHTML = originalContent;
                                buttonEl.classList.remove('bg-emerald-600');
                                buttonEl.classList.add('bg-slate-950', 'hover:bg-black');
                                buttonEl.disabled = false;
                            }, 1000);
                        });
                } else {
                    alert(data.message || 'Unable to add item to bag.');
                    buttonEl.innerHTML = originalContent;
                    buttonEl.disabled = false;
                }
            } catch (err) {
                console.error(err);
                alert('Connection error. Please try again.');
                buttonEl.innerHTML = originalContent;
                buttonEl.disabled = false;
            }
        }

        // Keyboard Shortcut: Press '/' to focus search input
        window.addEventListener('keydown', function(e) {
            if (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
                e.preventDefault();
                const searchEl = document.getElementById('live-search-input');
                if (searchEl) searchEl.focus();
            }
        });

        // Debounced Live Search Autocomplete
        const searchInput = document.getElementById('live-search-input');
        const searchDropdown = document.getElementById('search-autocomplete');
        let searchTimeout = null;

        if (searchInput && searchDropdown) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const query = this.value.trim();

                if (query.length < 2) {
                    searchDropdown.classList.add('hidden');
                    searchDropdown.innerHTML = '';
                    return;
                }

                searchTimeout = setTimeout(async () => {
                    try {
                        const currentCityId = {{ $currentCity?->id ?? 'null' }};
                        if (! currentCityId) {
                            searchDropdown.innerHTML = `
                                <li class="p-4 text-center text-xs text-amber-900 bg-amber-50">
                                    Please select your delivery city first to check live inventory.
                                </li>
                            `;
                            searchDropdown.classList.remove('hidden');
                            return;
                        }

                        const res = await fetch(`/api/products?city_id=${currentCityId}&q=${encodeURIComponent(query)}`);
                        const data = await res.json();

                        if (data.success && data.data && data.data.products && data.data.products.length > 0) {
                            searchDropdown.innerHTML = data.data.products.slice(0, 5).map(prod => `
                                <li>
                                    <a href="/products/${prod.slug}" class="flex items-center gap-3 p-3 hover:bg-slate-50 transition cursor-pointer">
                                        <div class="w-10 h-10 bg-slate-100 rounded-lg overflow-hidden flex items-center justify-center shrink-0 border border-slate-200">
                                            ${prod.image ? `<img src="${prod.image}" class="w-full h-full object-cover">` : '<svg class="w-5 h-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>'}
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <span class="font-bold text-xs text-slate-900 block truncate">${prod.name}</span>
                                            <span class="text-[10px] text-slate-500 font-mono">Rs ${Number(prod.primary_price).toLocaleString()} • ${prod.estimated_minutes}m</span>
                                        </div>
                                        <span class="text-[10px] font-bold ${prod.is_available ? 'text-emerald-700 bg-emerald-50 border border-emerald-200' : 'text-rose-700 bg-rose-50 border border-rose-200'} px-2 py-0.5 rounded-full">
                                            ${prod.is_available ? 'In Stock' : 'Out'}
                                        </span>
                                    </a>
                                </li>
                            `).join('');
                            searchDropdown.classList.remove('hidden');
                        } else {
                            searchDropdown.innerHTML = `
                                <li class="p-4 text-center text-xs text-slate-500">
                                    No products found for "${query}" in {{ $currentCity?->name ?? 'your city' }}.
                                </li>
                            `;
                            searchDropdown.classList.remove('hidden');
                        }
                    } catch (e) {
                        console.error('Search error:', e);
                    }
                }, 200);
            });

            // Close on click outside
            document.addEventListener('click', function(e) {
                if (! searchInput.contains(e.target) && ! searchDropdown.contains(e.target)) {
                    searchDropdown.classList.add('hidden');
                }
            });
        }

        @if(! $currentCity)
        // Auto-show city selection modal on initial load when customer has not chosen a city
        document.addEventListener('DOMContentLoaded', function() {
            const cityModal = document.getElementById('city-modal');
            if (cityModal && typeof cityModal.showModal === 'function') {
                cityModal.showModal();
            }
        });
        @endif
    </script>
</body>
</html>
