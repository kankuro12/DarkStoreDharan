@extends('layouts.storefront')

@section('content')
<div class="max-w-3xl mx-auto py-8 bg-white rounded-3xl p-6 sm:p-10 border border-slate-200 shadow-xs space-y-6 text-xs text-slate-700 leading-relaxed">
    <div class="border-b border-slate-100 pb-4">
        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-950">Privacy & Customer Data Protection Policy</h1>
        <p class="text-slate-500 mt-1">Last updated: September 2026 • DarkStore Technologies Nepal</p>
    </div>

    <div class="space-y-4 text-xs">
        <div>
            <h2 class="text-sm font-bold text-slate-900 mb-1.5">1. Personal Information We Collect</h2>
            <p>
                We collect your name, mobile contact number, physical delivery address, and area landmarks exclusively to fulfill ultra-fast 30-minute delivery orders. <strong>All sensitive customer Personally Identifiable Information (PII) including phone numbers and delivery coordinates are strictly encrypted at rest at the database column level</strong> using industry-standard AES-256-GCM encryption.
            </p>
        </div>

        <div>
            <h2 class="text-sm font-bold text-slate-900 mb-1.5">2. Geolocation and City-First Inventory Routing</h2>
            <p>
                Our platform operates on an isolated city-first architecture. Your selected city determines product catalog availability, localized pricing, and assigned local dark store micro-warehouses. We only use your location to calculate accurate delivery ETAs and coordinate assigned delivery riders for immediate dispatch.
            </p>
        </div>

        <div>
            <h2 class="text-sm font-bold text-slate-900 mb-1.5">3. Payment Information Security & Gateways</h2>
            <p>
                DarkStore Nepal never stores your credit/debit card details, PINs, or digital wallet credentials on our servers. All online transactions are processed through authorized payment gateway partners (eSewa, Khalti, Fonepay) via secure server-to-server cryptographic verification.
            </p>
        </div>

        <div>
            <h2 class="text-sm font-bold text-slate-900 mb-1.5">4. Data Deletion and Account Privacy Rights</h2>
            <p>
                Customers retain full rights to inspect, update, or permanently delete their personal information, delivery addresses, and account history. To submit a data erasure request, please reach out to our privacy compliance desk at <strong class="text-slate-900">privacy@darkstore.np</strong>.
            </p>
        </div>
    </div>
</div>
@endsection
