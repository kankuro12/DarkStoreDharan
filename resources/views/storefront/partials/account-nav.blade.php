{{-- Shared tab strip for the My Account section --}}
<div class="flex items-center gap-2 border-b border-slate-200 mb-6 overflow-x-auto">
    <a
        href="{{ route('storefront.account.orders') }}"
        class="px-4 py-2.5 text-xs font-bold whitespace-nowrap border-b-2 -mb-px transition {{ request()->routeIs('storefront.account.orders') ? 'border-slate-950 text-slate-950' : 'border-transparent text-slate-500 hover:text-slate-900' }}"
    >
        Order History
    </a>
    <a
        href="{{ route('storefront.account.addresses') }}"
        class="px-4 py-2.5 text-xs font-bold whitespace-nowrap border-b-2 -mb-px transition {{ request()->routeIs('storefront.account.addresses') ? 'border-slate-950 text-slate-950' : 'border-transparent text-slate-500 hover:text-slate-900' }}"
    >
        Saved Addresses
    </a>
</div>
