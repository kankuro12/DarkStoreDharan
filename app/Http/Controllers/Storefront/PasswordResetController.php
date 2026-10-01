<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Auth\OtpService;
use App\Services\Sms\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function __construct(
        protected OtpService $otpService
    ) {}

    public function showForgotForm(): View
    {
        return view('storefront.auth.forgot-password');
    }

    /**
     * Email flow: standard Laravel password broker sends a signed reset link.
     */
    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::broker('customers')->sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? back()->with('success', __($status))
            : back()->withErrors(['email' => __($status)]);
    }

    public function showResetForm(Request $request, string $token): View
    {
        return view('storefront.auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $status = Password::broker('customers')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Customer $customer, string $password) {
                $customer->forceFill(['password' => Hash::make($password)])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', 'Password reset. You can now log in.')
            : back()->withErrors(['email' => __($status)]);
    }

    /**
     * Phone flow, step 1: text an OTP to the account's registered phone number.
     */
    public function requestPhoneOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'min:10', 'max:15'],
        ]);

        $phone = SmsService::normalize($validated['phone']);

        if (! Customer::where('phone', $phone)->exists()) {
            // Don't reveal whether the phone is registered; respond the same way either way.
            return response()->json(['success' => true, 'message' => 'If that phone is registered, a code has been sent.']);
        }

        $result = $this->otpService->issue($phone, 'password_reset');

        return response()->json($result, $result['success'] ? 200 : 429);
    }

    /**
     * Phone flow, step 2: verify the OTP and set the new password directly.
     */
    public function resetViaPhone(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'min:10', 'max:15'],
            'code' => ['required', 'string', 'size:6'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $phone = SmsService::normalize($validated['phone']);

        if (! $this->otpService->verify($phone, 'password_reset', $validated['code'])) {
            return back()->withErrors(['code' => 'That code is invalid or has expired.'])->withInput();
        }

        $customer = Customer::where('phone', $phone)->first();

        if (! $customer) {
            return back()->withErrors(['phone' => 'No account found for that phone number.'])->withInput();
        }

        $customer->forceFill(['password' => Hash::make($validated['password'])])->save();

        return redirect()->route('login')->with('success', 'Password reset. You can now log in.');
    }
}
