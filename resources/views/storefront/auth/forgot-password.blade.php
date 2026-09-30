@extends('layouts.storefront')

@section('content')
<div class="max-w-md mx-auto my-12 bg-white p-8 rounded-3xl shadow-xl border border-slate-100">
    <div class="text-center mb-8">
        <h2 class="text-2xl font-extrabold text-slate-900 mb-2">Reset Your Password</h2>
        <p class="text-sm text-slate-500">Choose how you'd like to verify it's you</p>
    </div>

    <!-- Email / Phone Tab Switcher -->
    <div class="flex items-center gap-1 bg-slate-100 rounded-xl p-1 mb-6 text-xs font-bold">
        <button type="button" id="tab-email-btn" onclick="switchResetTab('email')" class="flex-1 py-2 rounded-lg transition cursor-pointer bg-white text-slate-950 shadow-xs">
            Via Email
        </button>
        <button type="button" id="tab-phone-btn" onclick="switchResetTab('phone')" class="flex-1 py-2 rounded-lg transition cursor-pointer text-slate-500">
            Via Phone
        </button>
    </div>

    <!-- Email Reset Link -->
    <div id="tab-email-panel">
        <p class="text-xs text-slate-500 mb-4">We'll email you a secure link to reset your password.</p>
        <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Email Address</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus class="w-full bg-slate-50 border border-slate-200 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 rounded-xl px-4 py-2.5 text-sm transition outline-none">
                @error('email')
                    <p class="text-rose-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="w-full bg-slate-950 hover:bg-black text-white font-bold text-sm py-3 px-4 rounded-xl transition shadow-md active:scale-95 cursor-pointer">
                Send Reset Link
            </button>
        </form>
    </div>

    <!-- Phone OTP Reset -->
    <div id="tab-phone-panel" class="hidden">
        <p class="text-xs text-slate-500 mb-4">We'll text a verification code to your registered phone number.</p>
        <form method="POST" action="{{ route('password.phone.reset') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Mobile Phone</label>
                <div class="flex gap-2">
                    <input type="tel" name="phone" id="reset-phone-input" required placeholder="98XXXXXXXX" value="{{ old('phone') }}" class="flex-1 bg-slate-50 border border-slate-200 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 rounded-xl px-4 py-2.5 text-sm transition outline-none">
                    <button type="button" id="reset-send-btn" onclick="requestPhoneResetOtp()" class="shrink-0 px-4 py-2.5 bg-slate-900 hover:bg-black text-white text-xs font-bold rounded-xl transition cursor-pointer">
                        Send Code
                    </button>
                </div>
                @error('phone')
                    <p class="text-rose-500 text-xs mt-1">{{ $message }}</p>
                @enderror
                <p id="reset-status-message" class="text-xs mt-1.5"></p>
            </div>

            <div id="reset-code-fields" class="{{ $errors->has('code') ? '' : 'hidden' }} space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">6-Digit Code</label>
                    <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="••••••" class="w-full bg-slate-50 border border-slate-200 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 rounded-xl px-4 py-2.5 text-sm tracking-[0.3em] font-mono text-center transition outline-none">
                    @error('code')
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
            </div>
        </form>
    </div>

    <div class="mt-6 text-center text-xs text-slate-600">
        <a href="{{ route('login') }}" class="text-amber-600 hover:text-amber-700 font-bold">Back to Log In</a>
    </div>
</div>

<script>
    function switchResetTab(tab) {
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

    async function requestPhoneResetOtp() {
        const phone = document.getElementById('reset-phone-input').value.trim();
        const statusEl = document.getElementById('reset-status-message');
        const btn = document.getElementById('reset-send-btn');

        if (phone.length < 10) {
            statusEl.textContent = 'Enter a valid 10-digit mobile number.';
            statusEl.className = 'text-xs mt-1.5 text-rose-600 font-semibold';
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Sending...';

        try {
            const response = await fetch('{{ route('password.phone.request') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ phone })
            });

            const data = await response.json();

            statusEl.textContent = data.message || 'If that phone is registered, a code has been sent.';
            statusEl.className = response.ok ? 'text-xs mt-1.5 text-emerald-700 font-semibold' : 'text-xs mt-1.5 text-rose-600 font-semibold';
            document.getElementById('reset-code-fields').classList.remove('hidden');
            btn.textContent = 'Resend Code';
        } catch (err) {
            statusEl.textContent = 'Connection error. Please try again.';
            statusEl.className = 'text-xs mt-1.5 text-rose-600 font-semibold';
            btn.textContent = 'Send Code';
        } finally {
            btn.disabled = false;
        }
    }

    @error('code')
        switchResetTab('phone');
    @enderror
    @error('phone')
        switchResetTab('phone');
    @enderror
</script>
@endsection
