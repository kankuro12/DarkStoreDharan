@extends('layouts.storefront')

@section('content')
<div class="max-w-md mx-auto my-12 bg-white p-8 rounded-3xl shadow-xl border border-slate-100">
    <div class="text-center mb-8">
        <h2 class="text-2xl font-extrabold text-slate-900 mb-2">Welcome Back</h2>
        <p class="text-sm text-slate-500">Log in to track orders and save your delivery details</p>
    </div>

    <!-- Social Logins -->
    <div class="space-y-3 mb-8">
        <a href="{{ route('social.redirect', 'google') }}" class="w-full flex items-center justify-center gap-3 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-semibold text-sm py-3 px-4 rounded-xl transition cursor-pointer">
            <svg class="w-5 h-5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
            Continue with Google
        </a>
    </div>

    <div class="relative flex items-center py-5">
        <div class="flex-grow border-t border-slate-200"></div>
        <span class="flex-shrink-0 mx-4 text-xs font-medium text-slate-400 uppercase tracking-wider">Or log in with email</span>
        <div class="flex-grow border-t border-slate-200"></div>
    </div>

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Email Address</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus class="w-full bg-slate-50 border border-slate-200 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 rounded-xl px-4 py-2.5 text-sm transition outline-none">
            @error('email')
                <p class="text-rose-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Password</label>
            <input type="password" name="password" required class="w-full bg-slate-50 border border-slate-200 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 rounded-xl px-4 py-2.5 text-sm transition outline-none">
            @error('password')
                <p class="text-rose-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="remember" class="rounded text-amber-500 focus:ring-amber-500 border-slate-300">
                <span class="text-xs text-slate-600">Remember me</span>
            </label>
            <a href="#" class="text-xs text-amber-600 hover:text-amber-700 font-semibold">Forgot Password?</a>
        </div>

        <button type="submit" class="w-full bg-slate-950 hover:bg-black text-white font-bold text-sm py-3 px-4 rounded-xl transition shadow-md active:scale-95 cursor-pointer">
            Log In
        </button>
    </form>

    <div class="mt-6 text-center text-xs text-slate-600">
        Don't have an account? <a href="{{ route('register') }}" class="text-amber-600 hover:text-amber-700 font-bold">Sign up</a>
    </div>
</div>
@endsection
