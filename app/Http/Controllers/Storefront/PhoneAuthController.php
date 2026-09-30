<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\OtpService;
use App\Services\Sms\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PhoneAuthController extends Controller
{
    public function __construct(
        protected OtpService $otpService
    ) {}

    /**
     * Step 1: text a one-time code to the given phone. Works for both sign-in and
     * sign-up — the same "Continue with Phone" flow branches on verify().
     */
    public function requestOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'min:10', 'max:15'],
        ]);

        $phone = SmsService::normalize($validated['phone']);

        if (strlen($phone) !== 10) {
            return response()->json(['success' => false, 'message' => 'Enter a valid 10-digit mobile number.'], 422);
        }

        $result = $this->otpService->issue($phone, 'login');

        return response()->json($result, $result['success'] ? 200 : 429);
    }

    /**
     * Step 2: verify the code. Logs the user in if the phone is already registered,
     * otherwise creates a new customer account for it (sign-in and sign-up unified).
     */
    public function verifyOtp(Request $request)
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'min:10', 'max:15'],
            'code' => ['required', 'string', 'size:6'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $phone = SmsService::normalize($validated['phone']);

        if (! $this->otpService->verify($phone, 'login', $validated['code'])) {
            return back()->withErrors(['code' => 'That code is invalid or has expired.'])->withInput();
        }

        $user = User::where('phone', $phone)->first();

        if (! $user) {
            $user = User::create([
                'name' => $validated['name'] ?: 'DarkStore Customer',
                'email' => 'phone-'.$phone.'-'.Str::random(6).'@phone.darkstore.local',
                'phone' => $phone,
                'phone_verified_at' => now(),
                'provider' => 'phone',
                'role' => UserRole::Customer,
            ]);
        } elseif (! $user->phone_verified_at) {
            $user->update(['phone_verified_at' => now()]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended('/');
    }
}
