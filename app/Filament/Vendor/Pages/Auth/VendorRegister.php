<?php

namespace App\Filament\Vendor\Pages\Auth;

use App\Enums\BusinessType;
use App\Enums\VerificationStatus;
use App\Filament\Shared\PhoneOtpFields;
use App\Models\City;
use App\Models\Vendor;
use Filament\Auth\Pages\Register;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use SensitiveParameter;

/** Step 0 of supplier onboarding: a phone-verified draft account, then the KYC wizard. */
class VendorRegister extends Register
{
    public const OTP_PURPOSE = 'vendor_signup';

    public function getHeading(): string
    {
        return 'Supply to DarkStore';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('legal_name')->label('Registered business name')->required()->maxLength(200),
            Select::make('business_type')->options(BusinessType::class)->required(),
            TextInput::make('pan_number')
                ->label('PAN (9 digits)')
                ->required()
                ->regex('/^\d{9}$/')
                ->unique(Vendor::class, 'pan_number'),
            ...PhoneOtpFields::make(Vendor::class, self::OTP_PURPOSE),
            TextInput::make('email')->label('Business email')->email()->required()->maxLength(255)->unique(Vendor::class, 'email'),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            Select::make('city_id')
                ->label('City of your pickup location')
                ->options(fn () => City::where('status', 'active')->pluck('name', 'id'))
                ->required(),
        ]);
    }

    protected function handleRegistration(#[SensitiveParameter] array $data): Model
    {
        PhoneOtpFields::assertVerified($data['phone'], $this->data['otp_code'] ?? null, self::OTP_PURPOSE);

        return Vendor::create([
            'legal_name' => $data['legal_name'],
            'business_type' => $data['business_type'],
            'pan_number' => $data['pan_number'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'password' => $data['password'],
            'city_id' => $data['city_id'],
            'status' => 'active',
            'verification_status' => VerificationStatus::Draft,
            'phone_verified_at' => now(),
        ]);
    }
}
