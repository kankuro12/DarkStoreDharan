<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     * Send a text message to a Nepali mobile number.
     * Returns true on success (or when logging in place of a real gateway).
     */
    public function send(string $to, string $message): bool
    {
        $to = self::normalize($to);
        $driver = config('services.sms.driver', 'log');

        return match ($driver) {
            'sparrow' => $this->sendViaSparrow($to, $message),
            default => $this->sendViaLog($to, $message),
        };
    }

    protected function sendViaLog(string $to, string $message): bool
    {
        Log::channel(config('logging.default'))->info('[SMS] To: '.$to.' | Message: '.$message);

        return true;
    }

    protected function sendViaSparrow(string $to, string $message): bool
    {
        $config = config('services.sms.sparrow');

        try {
            $response = Http::asForm()->post($config['url'], [
                'token' => $config['token'],
                'from' => $config['from'],
                'to' => $to,
                'text' => $message,
            ]);

            if (! $response->successful()) {
                Log::warning('SMS gateway request failed', ['to' => $to, 'status' => $response->status()]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('SMS gateway threw an exception', ['to' => $to, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Normalize to a bare 10-digit Nepali mobile number (strip +977, spaces, dashes).
     * Public/static so any part of the app (auth, OTP, checkout) matches numbers consistently.
     */
    public static function normalize(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number);

        if (str_starts_with($digits, '977') && strlen($digits) > 10) {
            $digits = substr($digits, 3);
        }

        return $digits;
    }
}
