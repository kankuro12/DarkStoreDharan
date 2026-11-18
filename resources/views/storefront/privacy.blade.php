@extends('layouts.storefront', [
    'title' => 'Privacy Policy - ' . ($storeSettings['store_name'] ?? 'DarkStore'),
])

@section('content')
@php
    $domain = preg_replace('/^www\./', '', parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost');
    $privacyEmail = 'privacy@'.$domain;
    $company = 'DarkStore Dharan Technologies Pvt. Ltd.';
    $brand = $storeSettings['store_name'] ?? 'DarkStore';
@endphp
<div class="max-w-3xl mx-auto py-8 bg-white rounded-3xl p-6 sm:p-10 border border-slate-200 space-y-6 text-sm text-slate-700 leading-relaxed">
    <header class="border-b border-slate-100 pb-4 space-y-1">
        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-950">Privacy Policy</h1>
        <p class="text-slate-500">Effective date: 18 November 2026 &bull; {{ $company }}</p>
    </header>

    <nav aria-label="Contents" class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
        <h2 class="font-bold text-slate-900 mb-2">Contents</h2>
        <ol class="list-decimal pl-5 grid sm:grid-cols-2 gap-x-6 gap-y-1">
            @foreach([
                'intro' => 'Introduction and scope', 'definitions' => 'Definitions', 'controller' => 'Who is responsible',
                'collect' => 'Personal data we collect', 'purposes' => 'Purposes and legal basis', 'sharing' => 'Disclosure to third parties',
                'retention' => 'Retention', 'security' => 'Security of personal data', 'rights' => 'Your rights',
                'cookies' => 'Cookies and device storage', 'children' => 'Children', 'breach' => 'Data breaches',
                'transfers' => 'Storage location and transfers', 'changes' => 'Changes to this policy', 'law' => 'Governing law and disputes',
                'contact' => 'Contact and grievances',
            ] as $id => $label)
                <li><a href="#{{ $id }}" class="text-slate-700 hover:text-slate-950 underline">{{ $label }}</a></li>
            @endforeach
        </ol>
    </nav>

    <section id="intro" class="space-y-2">
        <h2 class="text-base font-bold text-slate-950">1. Introduction and scope</h2>
        <p>1.1 This Privacy Policy explains how {{ $company }} (&ldquo;{{ $brand }}&rdquo;, &ldquo;we&rdquo;, &ldquo;us&rdquo;) collects, uses, stores, discloses and protects personal data when you use our website, mobile web app and related services (the &ldquo;Platform&rdquo;), whether as a customer, a delivery rider or a supplier.</p>
        <p>1.2 We process personal data in accordance with the Constitution of Nepal (Article 28, right to privacy), the Individual Privacy Act, 2075 (2018) and the Individual Privacy Regulations, 2077 (2020), the Electronic Transactions Act, 2063 (2008), the Consumer Protection Act, 2075 (2018) and other applicable laws of Nepal.</p>
        <p>1.3 By using the Platform you acknowledge that you have read this policy. Where the law requires your consent, we ask for it separately, and you may withdraw it at any time as described in Section 9.</p>
    </section>

    <section id="definitions" class="space-y-2">
        <h2 class="text-base font-bold text-slate-950">2. Definitions</h2>
        <ul class="list-disc pl-5 space-y-1">
            <li><strong>Personal data</strong> means any information relating to an identified or identifiable individual, including name, contact details, address, identity documents and location.</li>
            <li><strong>Sensitive personal data</strong> includes identity document numbers, financial account details and precise location.</li>
            <li><strong>Processing</strong> means any operation on personal data, including collection, recording, storage, use, disclosure and deletion.</li>
            <li><strong>Rider</strong> means an independent delivery partner registered on the Platform. <strong>Supplier</strong> means a business that supplies goods to us for sale.</li>
        </ul>
    </section>

    <section id="controller" class="space-y-2">
        <h2 class="text-base font-bold text-slate-950">3. Who is responsible</h2>
        <p>{{ $company }}, a company registered in Nepal, is responsible for the personal data processed through the Platform. Questions or requests about your personal data can be sent to our privacy desk at <a href="mailto:{{ $privacyEmail }}" class="font-bold text-slate-900 underline">{{ $privacyEmail }}</a>.</p>
    </section>

    <section id="collect" class="space-y-2">
        <h2 class="text-base font-bold text-slate-950">4. Personal data we collect</h2>
        <p><strong>4.1 Customers.</strong> Name; mobile number; email address (optional); password (stored only in hashed form); delivery addresses, landmarks and map coordinates; selected city; order, payment-status and return history; messages you send us.</p>
        <p><strong>4.2 Riders.</strong> In addition to account details: date of birth, gender, permanent and temporary address, selfie, citizenship or National ID details and images, driving license, vehicle bluebook and insurance details and images, optional police clearance, home hub and base location, payout bank or wallet details, and delivery activity records.</p>
        <p><strong>4.3 Suppliers.</strong> Legal and trade name, business type, PAN, VAT and registration numbers and certificates, food license where applicable, details and identity documents of the authorized person, pickup address and location, bank details, and supply and settlement records.</p>
        <p><strong>4.4 Collected automatically.</strong> IP address, browser and device type, pages viewed, and session and security logs needed to operate and protect the Platform.</p>
        <p><strong>4.5 From third parties.</strong> Payment confirmation from payment gateways (we never receive your wallet PIN or card number), and basic profile data if you choose to sign in with a social account.</p>
    </section>

    <section id="purposes" class="space-y-2">
        <h2 class="text-base font-bold text-slate-950">5. Purposes and legal basis</h2>
        <p>We process personal data only for specific, lawful purposes and only to the extent necessary:</p>
        <ul class="list-disc pl-5 space-y-1">
            <li><strong>Performing our contract with you:</strong> creating your account, taking and delivering orders, processing payments, cancellations, returns and refunds, and paying riders and suppliers.</li>
            <li><strong>Legal obligations:</strong> verifying the identity and eligibility of riders and suppliers (KYC), keeping tax and accounting records, and responding to lawful requests from authorities.</li>
            <li><strong>Legitimate interests:</strong> preventing fraud and abuse, securing accounts (including one-time SMS codes), improving the Platform, and resolving disputes.</li>
            <li><strong>Consent:</strong> optional marketing messages and any other processing for which we ask your consent.</li>
        </ul>
        <p>We do not sell personal data, and we do not use it for purposes incompatible with those above without telling you and, where required, obtaining your consent.</p>
    </section>

    <section id="sharing" class="space-y-2">
        <h2 class="text-base font-bold text-slate-950">6. Disclosure to third parties</h2>
        <p>We disclose personal data only as follows, and only what each recipient needs:</p>
        <ul class="list-disc pl-5 space-y-1">
            <li><strong>Riders</strong> assigned to your order receive your name, phone number, delivery address and the amount to collect, only to complete that delivery.</li>
            <li><strong>Suppliers</strong> receive aggregated sales and stock figures for their own products. They never receive customer names, phone numbers or addresses.</li>
            <li><strong>Service providers</strong> acting on our instructions: payment gateways (eSewa, Khalti, Fonepay), SMS and email providers, and hosting providers, each bound to protect the data and use it only for the service they provide to us.</li>
            <li><strong>Authorities</strong>, where required by law, court order or a lawful request from a competent government body.</li>
            <li><strong>Business transfers</strong>: a successor in a merger, acquisition or sale of assets, subject to this policy.</li>
        </ul>
    </section>

    <section id="retention" class="space-y-2">
        <h2 class="text-base font-bold text-slate-950">7. Retention</h2>
        <p>7.1 We keep personal data only as long as needed for the purposes in Section 5. Customer account data is kept while the account is active and deleted or anonymized after a verified deletion request, except as stated in 7.2.</p>
        <p>7.2 Order, invoice, payment and settlement records, and the KYC records of riders and suppliers, are kept for the period required by tax, accounting and other applicable laws, and for as long as needed to establish or defend legal claims. Rejected or abandoned KYC applications are deleted once no longer needed.</p>
    </section>

    <section id="security" class="space-y-3">
        <h2 class="text-base font-bold text-slate-950">8. Security of personal data</h2>
        <p>8.1 We maintain reasonable organizational and technical safeguards appropriate to the sensitivity of the data, and restrict access to staff who need it for their role. Staff are bound by confidentiality obligations.</p>
        <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4 space-y-2">
            <h3 class="font-semibold text-slate-900">8.2 Technical measures</h3>
            <ul class="list-disc pl-5 space-y-1">
                <li>All traffic between your device and the Platform is encrypted in transit (HTTPS/TLS).</li>
                <li>Delivery phone numbers and map coordinates, identity document numbers, and bank account numbers are encrypted at rest at the database column level (AES-256 with message authentication).</li>
                <li>Passwords are stored only as salted one-way hashes; one-time SMS codes are hashed, expire after 10 minutes and lock after repeated wrong attempts.</li>
                <li>KYC document images are kept in private storage that is not publicly reachable. Only authorized reviewers can open them, and every view or reveal of a document number is recorded in an audit log.</li>
                <li>Customer, rider, supplier and staff accounts use separate sign-in systems, so access to one does not grant access to another.</li>
                <li>Online payments are confirmed by server-to-server verification with the payment gateway; we never store card numbers, wallet PINs or passwords for payment accounts.</li>
                <li>Sign-in, code requests and other sensitive actions are rate-limited to resist guessing and abuse.</li>
            </ul>
        </div>
        <p>8.3 No method of transmission or storage is completely secure. You are responsible for keeping your password and one-time codes confidential; we will never ask for them by phone.</p>
    </section>

    <section id="rights" class="space-y-2">
        <h2 class="text-base font-bold text-slate-950">9. Your rights</h2>
        <p>Subject to applicable law, you have the right to:</p>
        <ul class="list-disc pl-5 space-y-1">
            <li>know what personal data we hold about you and obtain a copy;</li>
            <li>have inaccurate or incomplete data corrected (most details can be updated from your account);</li>
            <li>request deletion of your data, subject to the retention duties in Section 7;</li>
            <li>withdraw consent at any time, without affecting processing already carried out;</li>
            <li>object to processing based on our legitimate interests, and to direct marketing at any time;</li>
            <li>lodge a complaint with us and, if unresolved, with the competent authority under the laws of Nepal.</li>
        </ul>
        <p>Send requests to <a href="mailto:{{ $privacyEmail }}" class="font-bold text-slate-900 underline">{{ $privacyEmail }}</a>. We may need to verify your identity first and will respond within a reasonable time, normally within 30 days.</p>
    </section>

    <section id="cookies" class="space-y-2">
        <h2 class="text-base font-bold text-slate-950">10. Cookies and device storage</h2>
        <p>We use essential cookies to keep you signed in, remember your city and basket, and protect forms against forgery. We also store small values in your browser, for example to remember that you closed an announcement. We do not use third-party advertising cookies. Blocking essential cookies may stop the Platform from working.</p>
    </section>

    <section id="children" class="space-y-2">
        <h2 class="text-base font-bold text-slate-950">11. Children</h2>
        <p>The Platform is not intended for children under 16 years of age, and riders must be at least 18. We do not knowingly collect personal data from children; if you believe a child has provided us data, contact us and we will delete it.</p>
    </section>

    <section id="breach" class="space-y-2">
        <h2 class="text-base font-bold text-slate-950">12. Data breaches</h2>
        <p>If a security incident is likely to put your rights at risk, we will act promptly to contain it, notify affected individuals without undue delay, and notify authorities where required by law.</p>
    </section>

    <section id="transfers" class="space-y-2">
        <h2 class="text-base font-bold text-slate-950">13. Storage location and transfers</h2>
        <p>Personal data is stored on servers operated by our hosting providers. Where data is processed outside Nepal, we take steps to ensure it receives a level of protection consistent with this policy and the laws of Nepal.</p>
    </section>

    <section id="changes" class="space-y-2">
        <h2 class="text-base font-bold text-slate-950">14. Changes to this policy</h2>
        <p>We may update this policy from time to time. The effective date above shows the latest version. For material changes we will give notice on the Platform or by SMS or email before they take effect.</p>
    </section>

    <section id="law" class="space-y-2">
        <h2 class="text-base font-bold text-slate-950">15. Governing law and disputes</h2>
        <p>This policy is governed by the laws of Nepal. We encourage you to contact us first so we can try to resolve any concern. Disputes that cannot be resolved amicably fall under the jurisdiction of the competent courts of Nepal.</p>
    </section>

    <section id="contact" class="space-y-2">
        <h2 class="text-base font-bold text-slate-950">16. Contact and grievances</h2>
        <p>Privacy desk / grievance officer, {{ $company }}: <a href="mailto:{{ $privacyEmail }}" class="font-bold text-slate-900 underline">{{ $privacyEmail }}</a>. Other contact options are on our <a href="{{ route('storefront.contact') }}" class="font-bold text-slate-900 underline">Contact page</a>.</p>
    </section>
</div>
@endsection
