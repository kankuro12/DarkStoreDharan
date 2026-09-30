@extends('layouts.storefront')

@section('content')
<div class="max-w-md mx-auto my-12 bg-white p-8 rounded-3xl shadow-xl border border-slate-100">
    <div class="text-center mb-8">
        <h2 class="text-2xl font-extrabold text-slate-900 mb-2">Set a New Password</h2>
        <p class="text-sm text-slate-500">Choose a strong password for your account</p>
    </div>

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Email Address</label>
            <input type="email" name="email" value="{{ old('email', $email) }}" required autofocus class="w-full bg-slate-50 border border-slate-200 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 rounded-xl px-4 py-2.5 text-sm transition outline-none">
            @error('email')
                <p class="text-rose-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">New Password</label>
            <input type="password" name="password" required class="w-full bg-slate-50 border border-slate-200 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 rounded-xl px-4 py-2.5 text-sm transition outline-none">
            @error('password')
                <p class="text-rose-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Confirm New Password</label>
            <input type="password" name="password_confirmation" required class="w-full bg-slate-50 border border-slate-200 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 rounded-xl px-4 py-2.5 text-sm transition outline-none">
        </div>

        <button type="submit" class="w-full bg-slate-950 hover:bg-black text-white font-bold text-sm py-3 px-4 rounded-xl transition shadow-md active:scale-95 cursor-pointer">
            Reset Password
        </button>
    </form>
</div>
@endsection
