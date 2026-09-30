@extends('layouts.storefront')

@section('content')
<div class="max-w-md mx-auto my-12 bg-white p-8 rounded-3xl shadow-xl border border-slate-100">
    <div class="text-center mb-8">
        <h2 class="text-2xl font-extrabold text-slate-900 mb-2">Welcome Back</h2>
        <p class="text-sm text-slate-500">Log in to track orders and save your delivery details</p>
    </div>

    <!-- Social Logins -->
    <div class="space-y-3 mb-6">
        <a href="{{ route('social.redirect', 'google') }}" class="w-full flex items-center justify-center gap-3 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-semibold text-sm py-3 px-4 rounded-xl transition cursor-pointer">
            <svg class="w-5 h-5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
            Continue with Google
        </a>
    </div>

    <!-- Email / Phone Tab Switcher -->
    <div class="flex items-center gap-1 bg-slate-100 rounded-xl p-1 mb-6 text-xs font-bold">
        <button type="button" id="tab-email-btn" onclick="switchLoginTab('email')" class="flex-1 py-2 rounded-lg transition cursor-pointer bg-white text-slate-950 shadow-xs">
            Email &amp; Password
        </button>
        <button type="button" id="tab-phone-btn" onclick="switchLoginTab('phone')" class="flex-1 py-2 rounded-lg transition cursor-pointer text-slate-500">
            Phone (OTP)
        </button>
    </div>

    <!-- Email + Password Login -->
    <div id="tab-email-panel">
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
                <a href="{{ route('password.request') }}" class="text-xs text-amber-600 hover:text-amber-700 font-semibold">Forgot Password?</a>
            </div>

            <button type="submit" class="w-full bg-slate-950 hover:bg-black text-white font-bold text-sm py-3 px-4 rounded-xl transition shadow-md active:scale-95 cursor-pointer">
                Log In
            </button>
        </form>
    </div>

    <!-- Phone + OTP Login / Sign-up -->
    <div id="tab-phone-panel" class="hidden">
        <form method="POST" action="{{ route('otp.verify') }}" class="space-y-4" id="otp-verify-form">
            @csrf
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Mobile Phone</label>
                <div class="flex gap-2">
                    <input type="tel" name="phone" id="otp-phone-input" required placeholder="98XXXXXXXX" class="flex-1 bg-slate-50 border border-slate-200 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 rounded-xl px-4 py-2.5 text-sm transition outline-none">
                    <button type="button" id="otp-send-btn" onclick="requestPhoneOtp()" class="shrink-0 px-4 py-2.5 bg-slate-900 hover:bg-black text-white text-xs font-bold rounded-xl transition cursor-pointer">
                        Send Code
                    </button>
                </div>
                @error('phone')
                    <p class="text-rose-500 text-xs mt-1">{{ $message }}</p>
                @enderror
                <p id="otp-status-message" class="text-xs mt-1.5"></p>
            </div>

            <div id="otp-code-fields" class="hidden space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">6-Digit Code</label>
                    <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="••••••" class="w-full bg-slate-50 border border-slate-200 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 rounded-xl px-4 py-2.5 text-sm tracking-[0.3em] font-mono text-center transition outline-none">
                    @error('code')
                        <p class="text-rose-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Your Name (new accounts only)</label>
                    <input type="text" name="name" placeholder="e.g. Suman Shrestha" class="w-full bg-slate-50 border border-slate-200 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 rounded-xl px-4 py-2.5 text-sm transition outline-none">
                </div>
                <button type="submit" class="w-full bg-slate-950 hover:bg-black text-white font-bold text-sm py-3 px-4 rounded-xl transition shadow-md active:scale-95 cursor-pointer">
                    Verify &amp; Continue
                </button>
            </div>
        </form>
    </div>

    <div class="mt-6 text-center text-xs text-slate-600">
        Don't have an account? <a href="{{ route('register') }}" class="text-amber-600 hover:text-amber-700 font-bold">Sign up</a>
    </div>
</div>

<script>
    function switchLoginTab(tab) {
        const emailPanel = document.getElementById('tab-email-panel');
        const phonePanel = document.getElementById('tab-phone-panel');
        const emailBtn = document.getElementById('tab-email-btn');
        const phoneBtn = document.getElementById('tab-phone-btn');

        const activeClasses = ['bg-white', 'text-slate-950', 'shadow-xs'];
        const inactiveClasses = ['text-slate-500'];

        if (tab === 'phone') {
            emailPanel.classList.add('hidden');
            phonePanel.classList.remove('hidden');
            phoneBtn.classList.add(...activeClasses);
            phoneBtn.classList.remove(...inactiveClasses);
            emailBtn.classList.remove(...activeClasses);
            emailBtn.classList.add(...inactiveClasses);
        } else {
            phonePanel.classList.add('hidden');
            emailPanel.classList.remove('hidden');
            emailBtn.classList.add(...activeClasses);
            emailBtn.classList.remove(...inactiveClasses);
            phoneBtn.classList.remove(...activeClasses);
            phoneBtn.classList.add(...inactiveClasses);
        }
    }

    async function requestPhoneOtp() {
        const phone = document.getElementById('otp-phone-input').value.trim();
        const statusEl = document.getElementById('otp-status-message');
        const btn = document.getElementById('otp-send-btn');

        if (phone.length < 10) {
            statusEl.textContent = 'Enter a valid 10-digit mobile number.';
            statusEl.className = 'text-xs mt-1.5 text-rose-600 font-semibold';
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Sending...';

        try {
            const response = await fetch('{{ route('otp.request') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ phone })
            });

            const data = await response.json();

            if (response.ok && data.success) {
                statusEl.textContent = data.message || 'Code sent!';
                statusEl.className = 'text-xs mt-1.5 text-emerald-700 font-semibold';
                document.getElementById('otp-code-fields').classList.remove('hidden');
                btn.textContent = 'Resend Code';
            } else {
                statusEl.textContent = data.message || 'Unable to send code. Please try again.';
                statusEl.className = 'text-xs mt-1.5 text-rose-600 font-semibold';
                btn.textContent = 'Send Code';
            }
        } catch (err) {
            statusEl.textContent = 'Connection error. Please try again.';
            statusEl.className = 'text-xs mt-1.5 text-rose-600 font-semibold';
            btn.textContent = 'Send Code';
        } finally {
            btn.disabled = false;
        }
    }

    @error('code')
        switchLoginTab('phone');
        document.getElementById('otp-code-fields').classList.remove('hidden');
    @enderror
</script>
@endsection
