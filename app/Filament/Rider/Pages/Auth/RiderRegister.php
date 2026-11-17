<?php

namespace App\Filament\Rider\Pages\Auth;

use App\Enums\VehicleType;
use App\Enums\VerificationStatus;
use App\Filament\Shared\PhoneOtpFields;
use App\Models\City;
use App\Models\DeliveryAgent;
use Filament\Auth\Pages\Register;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use SensitiveParameter;

/** Step 0 of rider onboarding: create a phone-verified draft account, then continue in the KYC wizard. */
class RiderRegister extends Register
{
    public const OTP_PURPOSE = 'rider_signup';

    public function getHeading(): string
    {
        return 'Become a DarkStore rider';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getNameFormComponent()->label('Full name (as on citizenship)'),
            ...PhoneOtpFields::make(DeliveryAgent::class, self::OTP_PURPOSE),
            TextInput::make('email')
                ->label('Email (optional)')
                ->email()
                ->maxLength(255)
                ->unique(DeliveryAgent::class, 'email'),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            Select::make('city_id')
                ->label('City you will deliver in')
                ->options(fn () => City::where('status', 'active')->pluck('name', 'id'))
                ->required(),
            Select::make('vehicle_type')
                ->label('Vehicle')
                ->options(VehicleType::class)
                ->required(),
        ]);
    }

    protected function handleRegistration(#[SensitiveParameter] array $data): Model
    {
        PhoneOtpFields::assertVerified($data['phone'], $this->data['otp_code'] ?? null, self::OTP_PURPOSE);

        return DeliveryAgent::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'password' => $data['password'],
            'city_id' => $data['city_id'],
            'vehicle_type' => $data['vehicle_type'],
            'status' => 'active',
            'availability' => 'offline',
            'verification_status' => VerificationStatus::Draft,
            'phone_verified_at' => now(),
        ]);
    }
}
