@extends('layouts.storefront', [
    'title' => 'Help & How-to - ' . ($storeSettings['store_name'] ?? 'DarkStore'),
    'metaDescription' => 'How to order, pay, track and return, and how to become a delivery rider or supply products to ' . ($storeSettings['store_name'] ?? 'DarkStore') . '.',
])

@section('content')
<div class="max-w-3xl mx-auto py-8 space-y-6 text-sm text-slate-700 leading-relaxed">
    <header class="bg-white rounded-3xl p-6 sm:p-10 border border-slate-200 space-y-4">
        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-950">Help &amp; How-to</h1>
        <p class="text-slate-500">Everything you need to shop with us, and how to join us as a delivery rider or supplier.</p>
        <nav aria-label="Help topics" class="grid grid-cols-2 sm:grid-cols-3 gap-2 pt-2">
            @foreach([
                'ordering' => 'How to order',
                'delivery' => 'Delivery & cities',
                'payments' => 'Payments',
                'cancel-return' => 'Cancel & return',
                'account' => 'Your account',
                'become-rider' => 'Become a rider',
                'become-supplier' => 'Supply products',
                'contact-help' => 'Still need help?',
            ] as $id => $label)
                <a href="#{{ $id }}" class="px-3 py-2 rounded-xl border border-slate-200 text-slate-700 font-semibold text-xs hover:bg-slate-50 hover:text-slate-950 transition">{{ $label }}</a>
            @endforeach
        </nav>
    </header>

    <section id="ordering" class="bg-white rounded-3xl p-6 sm:p-10 border border-slate-200 space-y-3">
        <h2 class="text-lg font-bold text-slate-950">How to order</h2>
        <ol class="list-decimal pl-5 space-y-1.5">
            <li><strong>Choose your city.</strong> Prices and stock depend on the city you deliver to, so pick it first. You can change it from the top of any page.</li>
            <li><strong>Add items to your basket.</strong> Search or browse categories, choose a pack size and tap <em>Add</em>.</li>
            <li><strong>Check out.</strong> Enter or pick a saved delivery address, choose how to pay and place the order. Apply a coupon in the basket if you have one.</li>
            <li><strong>Track it.</strong> You get an order number; open its tracking page to follow it from packing to your door.</li>
        </ol>
    </section>

    <section id="delivery" class="bg-white rounded-3xl p-6 sm:p-10 border border-slate-200 space-y-3">
        <h2 class="text-lg font-bold text-slate-950">Delivery &amp; cities</h2>
        <p>Orders are packed at a small warehouse near you and delivered by our own riders, usually within 15&ndash;30 minutes. The estimate for each city is shown in the footer and at checkout.</p>
        <p>Very bad weather, strikes or road closures can slow deliveries. If your rider can&rsquo;t reach you, they will call the number on your order and try again.</p>
        @if($allCities->isNotEmpty())
            <p>We currently deliver in: <strong>{{ $allCities->pluck('name')->join(', ', ' and ') }}</strong>.</p>
        @endif
    </section>

    <section id="payments" class="bg-white rounded-3xl p-6 sm:p-10 border border-slate-200 space-y-3">
        <h2 class="text-lg font-bold text-slate-950">Payments</h2>
        <ul class="list-disc pl-5 space-y-1.5">
            <li><strong>Cash on Delivery</strong> for orders up to Rs 15,000. Please keep change ready.</li>
            <li><strong>eSewa, Khalti and Fonepay</strong> for online payment. You are sent to the provider&rsquo;s own page to pay; we never see your wallet PIN or card details.</li>
            <li>Some items can only be paid online; checkout tells you if so.</li>
        </ul>
    </section>

    <section id="cancel-return" class="bg-white rounded-3xl p-6 sm:p-10 border border-slate-200 space-y-3">
        <h2 class="text-lg font-bold text-slate-950">Cancel &amp; return</h2>
        <details class="rounded-xl border border-slate-200 p-4">
            <summary class="font-semibold text-slate-900 cursor-pointer">How do I cancel an order?</summary>
            <p class="mt-2">Open the order&rsquo;s tracking page and tap <em>Cancel order</em>. You can cancel any time before the order is packed for dispatch.</p>
        </details>
        <details class="rounded-xl border border-slate-200 p-4">
            <summary class="font-semibold text-slate-900 cursor-pointer">How do I return an item?</summary>
            <p class="mt-2">From the tracking page of a delivered order, request a return and tell us what was wrong. Fresh, dairy and other perishable items must be reported within 24 hours of delivery; packaged goods within 7 days.</p>
        </details>
        <details class="rounded-xl border border-slate-200 p-4">
            <summary class="font-semibold text-slate-900 cursor-pointer">When do I get my money back?</summary>
            <p class="mt-2">Once the return is approved, online payments are refunded to the original payment method and cash orders are settled in cash. Damaged, leaking or faulty items are refunded in full.</p>
        </details>
    </section>

    <section id="account" class="bg-white rounded-3xl p-6 sm:p-10 border border-slate-200 space-y-3">
        <h2 class="text-lg font-bold text-slate-950">Your account</h2>
        <ul class="list-disc pl-5 space-y-1.5">
            <li><strong>Sign in with your mobile number:</strong> we text a 6-digit code. A new number creates your account automatically.</li>
            <li>You can also sign in with email and password. Forgot it? Use <em>Forgot password</em> to get a reset link by email or a code by SMS.</li>
            <li>Save addresses, see past orders and reorder in one tap from your account.</li>
            <li>We will never ask for your code or password by phone. Don&rsquo;t share them with anyone.</li>
        </ul>
    </section>

    <section id="become-rider" class="bg-white rounded-3xl p-6 sm:p-10 border border-slate-200 space-y-3">
        <h2 class="text-lg font-bold text-slate-950">How to become a delivery rider</h2>
        <p>Earn by delivering orders in your city on your own bicycle, scooter or motorcycle.</p>
        <h3 class="font-semibold text-slate-900">You need</h3>
        <ul class="list-disc pl-5 space-y-1.5">
            <li>To be at least 18 years old, with a smartphone and a Nepali mobile number.</li>
            <li>Citizenship certificate (Nagarikta) or National ID card, and a clear selfie.</li>
            <li>For a scooter or motorcycle: a valid driving license (category A, or K for scooters), the vehicle&rsquo;s bluebook with tax paid, and valid third-party insurance.</li>
            <li>A bank account or a digital wallet for your payouts.</li>
        </ul>
        <h3 class="font-semibold text-slate-900">Steps</h3>
        <ol class="list-decimal pl-5 space-y-1.5">
            <li><a href="{{ url('/rider/register') }}" class="font-semibold text-slate-950 underline">Sign up as a rider</a> with your mobile number (we text you a code), city and vehicle type.</li>
            <li>Complete verification: personal details and address, identity documents, vehicle documents (not needed for bicycles), your home hub and payout details, then accept the rider agreement.</li>
            <li>Our team checks your documents. If something needs fixing you&rsquo;ll get an SMS and only need to re-upload that document.</li>
            <li>Once approved, attend a short onboarding session. After that, orders start appearing in your rider app at <a href="{{ url('/rider/login') }}" class="font-semibold text-slate-950 underline">Rider Login</a>.</li>
        </ol>
        <p class="text-slate-500">Keep your license, bluebook and insurance up to date: we remind you 30 and 7 days before they expire, and your account pauses if a required document expires until you upload the renewal.</p>
    </section>

    <section id="become-supplier" class="bg-white rounded-3xl p-6 sm:p-10 border border-slate-200 space-y-3">
        <h2 class="text-lg font-bold text-slate-950">How to supply products to us</h2>
        <p>Local producers, wholesalers and brands can supply products that we stock and sell in our store. You set your supply price; we handle storage, selling, delivery and customer service.</p>
        <h3 class="font-semibold text-slate-900">You need</h3>
        <ul class="list-disc pl-5 space-y-1.5">
            <li>A PAN certificate from the Inland Revenue Department.</li>
            <li>Business registration: company registration (OCR) for companies, or firm registration for sole proprietors and partnerships.</li>
            <li>A VAT certificate if you are VAT registered, and a food license (DFTQC) if you supply food.</li>
            <li>Citizenship or National ID of the person signing for the business, and a bank account in the business&rsquo;s name.</li>
        </ul>
        <h3 class="font-semibold text-slate-900">Steps</h3>
        <ol class="list-decimal pl-5 space-y-1.5">
            <li><a href="{{ url('/vendor/register') }}" class="font-semibold text-slate-950 underline">Register as a supplier</a> with your business name, PAN, business type and verified mobile number.</li>
            <li>Complete verification: business details and documents, authorized person, pickup address, payout account, then accept the supplier agreement.</li>
            <li>After approval, submit your products with pack sizes and your supply price. Our catalog team reviews each one and sets the selling price before it goes live.</li>
            <li>Track your stock and sales from <a href="{{ url('/vendor/login') }}" class="font-semibold text-slate-950 underline">Supplier Login</a>. You are paid your supply price for every unit in a delivered order; statements and payments are shown in your account.</li>
        </ol>
    </section>

    <section id="contact-help" class="bg-white rounded-3xl p-6 sm:p-10 border border-slate-200 space-y-3">
        <h2 class="text-lg font-bold text-slate-950">Still need help?</h2>
        <p>Visit our <a href="{{ route('storefront.contact') }}" class="font-semibold text-slate-950 underline">Contact page</a>. You can also read our <a href="{{ route('storefront.terms') }}" class="font-semibold text-slate-950 underline">Terms of Service</a> and <a href="{{ route('storefront.privacy') }}" class="font-semibold text-slate-950 underline">Privacy Policy</a>.</p>
    </section>
</div>
@endsection
