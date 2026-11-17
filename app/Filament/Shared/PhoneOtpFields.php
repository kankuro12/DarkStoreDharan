<?php

namespace App\Filament\Shared;

use App\Services\Auth\OtpService;
use App\Services\Sms\SmsService;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/** Phone + one-time-code fields for rider / vendor self-registration. */
class PhoneOtpFields
{
    public const NEPALI_MOBILE = '/^9[678]\d{8}$/';

    public const SENDS_PER_IP_PER_MINUTE = 5;

    /**
     * @param  class-string  $model  model whose `phone` column must stay unique
     */
    public static function make(string $model, string $otpPurpose): array
    {
        return [
            TextInput::make('phone')
                ->label('Mobile number')
                ->tel()
                ->required()
                ->maxLength(20)
                ->dehydrateStateUsing(fn (?string $state) => SmsService::normalize((string) $state))
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($model) {
                    $phone = SmsService::normalize((string) $value);

                    if (! preg_match(self::NEPALI_MOBILE, $phone)) {
                        $fail('Enter a valid 10-digit Nepali mobile number (98/97/96...).');
                    } elseif ($model::where('phone', $phone)->exists()) {
                        $fail('An account with this mobile number already exists. Please log in.');
                    }
                })
                ->suffixAction(
                    Action::make('sendOtp')
                        ->label('Send code')
                        ->icon('heroicon-o-chat-bubble-left-ellipsis')
                        ->action(function (Get $get) use ($otpPurpose) {
                            $phone = SmsService::normalize((string) $get('phone'));

                            if (! preg_match(self::NEPALI_MOBILE, $phone)) {
                                Notification::make()->title('Enter a valid mobile number first.')->danger()->send();

                                return;
                            }

                            // OtpService throttles per phone; also cap per IP so one client can't text many numbers.
                            $ipKey = 'otp-send-ip:'.request()->ip();
                            if (RateLimiter::tooManyAttempts($ipKey, self::SENDS_PER_IP_PER_MINUTE)) {
                                Notification::make()->title('Too many codes requested. Try again in a minute.')->danger()->send();

                                return;
                            }
                            RateLimiter::hit($ipKey, 60);

                            $result = app(OtpService::class)->issue($phone, $otpPurpose);
                            Notification::make()->title($result['message'])->{$result['success'] ? 'success' : 'warning'}()->send();
                        })
                ),

            TextInput::make('otp_code')
                ->label('Verification code')
                ->helperText('Tap "Send code" and enter the 6-digit code we text you.')
                ->required()
                ->length(6)
                ->dehydrated(false),
        ];
    }

    /** Throws a form validation error on the code field unless it is valid for this phone. */
    public static function assertVerified(string $phone, ?string $code, string $otpPurpose): void
    {
        if (! app(OtpService::class)->verify($phone, $otpPurpose, (string) $code)) {
            throw ValidationException::withMessages(['data.otp_code' => 'That code is invalid or has expired.']);
        }
    }
}
