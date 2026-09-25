@extends('layouts.storefront', [
    'title' => 'Contact Us - ' . ($storeSettings['store_name'] ?? 'DarkStore'),
    'metaDescription' => 'Get in touch with ' . ($storeSettings['store_name'] ?? 'DarkStore') . '. ' . Str::limit($storeSettings['contact_page_content'] ?? '', 100)
])

@section('content')
<div class="max-w-3xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
    <div class="bg-white rounded-3xl p-8 sm:p-12 border border-slate-200 shadow-sm text-center space-y-6">
        <div class="w-16 h-16 rounded-2xl bg-amber-50 text-amber-500 flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
        </div>
        
        <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-950 tracking-tight">Contact Us</h1>
        
        <div class="text-slate-600 prose prose-slate mx-auto">
            {!! nl2br(e($storeSettings['contact_page_content'] ?? 'Please reach out to our support team for any inquiries.')) !!}
        </div>
        
        <div class="pt-8 mt-8 border-t border-slate-100 flex justify-center gap-4">
            <a href="{{ route('storefront.home') }}" class="px-6 py-3 rounded-xl bg-slate-950 text-white font-bold text-sm hover:bg-black transition cursor-pointer shadow-sm">
                Continue Shopping
            </a>
        </div>
    </div>
</div>
@endsection
