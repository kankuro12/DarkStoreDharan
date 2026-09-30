<?php

namespace App\Services\Auth;

use App\Models\OtpCode;
use App\Services\Sms\SmsService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class OtpService
{
    public function __construct(
        protected SmsService $smsService
    ) {}

    /**
     * Generate and text a 6-digit OTP for the given phone/purpose.
     * Throttled to one request per 45 seconds per phone+purpose to prevent SMS spam/abuse.
     *
     * @return array{success: bool, message: string, retry_after?: int}
     */
    public function issue(string $phone, string $purpose): array
    {
        $throttleKey = "otp:{$purpose}:{$phone}";

        if (RateLimiter::tooManyAttempts($throttleKey, 1)) {
            return [
                'success' => false,
                'message' => 'Please wait before requesting another code.',
                'retry_after' => RateLimiter::availableIn($throttleKey),
            ];
        }

        $code = (string) random_int(100000, 999999);

        OtpCode::create([
            'phone' => $phone,
            'purpose' => $purpose,
            'code' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
        ]);

        RateLimiter::hit($throttleKey, 45);

        $this->smsService->send($phone, "Your DarkStore verification code is {$code}. It expires in 10 minutes.");

        return ['success' => true, 'message' => 'Verification code sent.'];
    }

    /**
     * Verify a code for phone/purpose. Consumes it on success so it can't be reused.
     * Locks the OTP out after 5 wrong attempts to prevent brute-forcing a 6-digit code.
     */
    public function verify(string $phone, string $purpose, string $code): bool
    {
        $otp = OtpCode::where('phone', $phone)
            ->where('purpose', $purpose)
            ->valid()
            ->latest()
            ->first();

        if (! $otp || $otp->attempts >= 5) {
            return false;
        }

        if (! $otp->matches($code)) {
            $otp->increment('attempts');

            return false;
        }

        $otp->update(['consumed_at' => now()]);

        return true;
    }
}
