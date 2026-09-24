@extends('layouts.storefront')

@section('content')
<div class="max-w-3xl mx-auto py-8 bg-white rounded-3xl p-6 sm:p-10 border border-slate-200 shadow-xs space-y-6 text-xs text-slate-700 leading-relaxed">
    <div class="border-b border-slate-100 pb-4">
        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-950">Terms of Service</h1>
        <p class="text-slate-500 mt-1">Last updated: September 2026 • DarkStore Technologies Nepal</p>
    </div>

    <div class="space-y-4 text-xs">
        <div>
            <h2 class="text-sm font-bold text-slate-900 mb-1.5">1. Cash on Delivery (COD) Rules and Limits</h2>
            <p>
                Cash on Delivery is provided as a convenience for eligible orders. COD orders are subject to a maximum threshold of Rs 15,000. Orders exceeding this limit must be paid online via digital wallet (eSewa, Khalti, or Fonepay). Customers who repeatedly refuse confirmed COD deliveries without valid justification will have COD payment privileges disabled on their account.
            </p>
        </div>

        <div>
            <h2 class="text-sm font-bold text-slate-900 mb-1.5">2. Delivery Service Level Agreements (SLAs)</h2>
            <p>
                Promised delivery times (typically 15–30 minutes) are realistic estimates calculated based on local dark store warehouse proximity, current order volume, and traffic conditions. While our riders prioritize rapid, safe dispatch, occasional delays caused by severe weather or major road blockades in Koshi Province do not constitute a breach of service.
            </p>
        </div>

        <div>
            <h2 class="text-sm font-bold text-slate-900 mb-1.5">3. Returns and Refunds Policy</h2>
            <p>
                Return requests must be submitted within 24 hours of delivery for perishable grocery and dairy items, and within 7 days for packaged goods. Items returned due to transit damage, leakage, or defect will be inspected at our fulfillment center and refunded in full via the original payment method or cash reconciliation.
            </p>
        </div>

        <div>
            <h2 class="text-sm font-bold text-slate-900 mb-1.5">4. City Inventory and Price Disclaimers</h2>
            <p>
                Prices, product availability, and promotional offers are specific to the customer's selected delivery city and assigned dark store. Changing delivery cities during shopping may alter product availability and basket totals.
            </p>
        </div>
    </div>
</div>
@endsection
