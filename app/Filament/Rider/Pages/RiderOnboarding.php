<?php

namespace App\Filament\Rider\Pages;

use App\Enums\KycDocumentType;
use App\Enums\PayoutMethod;
use App\Filament\Shared\KycFormSchemas;
use App\Filament\Shared\KycOnboardingPage;
use App\Filament\Shared\PhoneOtpFields;
use App\Models\DeliveryAgent;
use App\Models\Warehouse;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class RiderOnboarding extends KycOnboardingPage
{
    protected static ?string $title = 'Rider verification';

    protected static ?string $navigationLabel = 'Verification';

    protected static ?string $slug = 'onboarding';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    public static function shouldRegisterNavigation(): bool
    {
        return ! auth('delivery')->user()?->isApproved();
    }

    protected function party(): Model
    {
        return auth('delivery')->user();
    }

    protected function profileFields(): array
    {
        return [
            'name', 'date_of_birth', 'gender',
            'perm_province', 'perm_district', 'perm_municipality', 'perm_ward', 'perm_tole',
            'temp_province', 'temp_district', 'temp_municipality', 'temp_ward', 'temp_tole',
            'emergency_contact_name', 'emergency_contact_relation', 'emergency_contact_phone',
            'home_warehouse_id', 'base_latitude', 'base_longitude',
            'payout_method', 'bank_name', 'bank_account_name', 'bank_account_number', 'wallet_id',
        ];
    }

    protected function metaFields(): array
    {
        return ['license_category', 'vehicle_registration_number'];
    }

    protected function agreementText(): string
    {
        return config('kyc.rider_agreement');
    }

    protected function statusPageUrl(): string
    {
        return RiderAccountStatus::getUrl();
    }

    private function rider(): DeliveryAgent
    {
        return $this->party();
    }

    protected function steps(): array
    {
        $locked = $this->profileLocked();
        $dir = $this->documentDirectory();

        return [
            Step::make('Personal details')->schema([
                TextInput::make('name')->label('Full name (as on citizenship)')->required()->maxLength(150)->disabled($locked),
                DatePicker::make('date_of_birth')
                    ->label('Date of birth (AD)')
                    ->required()
                    ->beforeOrEqual(now()->subYears(18)->toDateString())
                    ->validationMessages(['before_or_equal' => 'Riders must be at least 18 years old.'])
                    ->disabled($locked),
                Select::make('gender')->options(['male' => 'Male', 'female' => 'Female', 'other' => 'Other'])->required()->disabled($locked),
                Section::make('Permanent address')->schema(KycFormSchemas::addressFields('perm', true, $locked))->columns(2),
                Section::make('Temporary address (if different)')->schema(KycFormSchemas::addressFields('temp', false, $locked))->columns(2)->collapsed(),
                Section::make('Emergency contact')->schema([
                    TextInput::make('emergency_contact_name')->label('Name')->required()->maxLength(150)->disabled($locked),
                    TextInput::make('emergency_contact_relation')->label('Relation')->required()->maxLength(50)->disabled($locked),
                    TextInput::make('emergency_contact_phone')->label('Mobile')->required()->regex(PhoneOtpFields::NEPALI_MOBILE)->disabled($locked),
                ])->columns(3),
            ]),

            Step::make('Identity')->schema([
                KycFormSchemas::documentSection(KycDocumentType::ProfilePhoto, $dir, true, $this->documentLocked(KycDocumentType::ProfilePhoto),
                    description: 'A clear, recent selfie with your face fully visible.'),
                ...$this->identityDocumentFields('Identity document'),
                KycFormSchemas::documentSection(KycDocumentType::PoliceClearance, $dir, false, $this->documentLocked(KycDocumentType::PoliceClearance), [
                    DatePicker::make('docs.police_clearance.issued_on')->label('Issue date (AD)')->beforeOrEqual('today')
                        ->disabled($this->documentLocked(KycDocumentType::PoliceClearance)),
                ], 'Optional, but speeds up approval.')->collapsed(),
            ]),

            Step::make('Vehicle')
                ->visible(fn () => (bool) $this->rider()->vehicle_type?->isMotorized())
                ->schema([
                    KycFormSchemas::documentSection(KycDocumentType::DrivingLicense, $dir, true, $this->documentLocked(KycDocumentType::DrivingLicense), [
                        Select::make('docs.driving_license.license_category')
                            ->label('License category')
                            ->options(fn () => collect($this->rider()->vehicle_type?->acceptedLicenseCategories() ?? [])->mapWithKeys(fn ($c) => [$c => $c]))
                            ->required()
                            ->disabled($this->documentLocked(KycDocumentType::DrivingLicense)),
                    ]),
                    KycFormSchemas::documentSection(KycDocumentType::VehicleBluebook, $dir, true, $this->documentLocked(KycDocumentType::VehicleBluebook), [
                        TextInput::make('docs.vehicle_bluebook.vehicle_registration_number')
                            ->label('Vehicle registration number (e.g. KO 1 PA 1234)')
                            ->required()
                            ->maxLength(30)
                            ->dehydrateStateUsing(fn (?string $state) => strtoupper(trim((string) $state)))
                            ->disabled($this->documentLocked(KycDocumentType::VehicleBluebook)),
                    ], 'Upload the owner details page (front) and the latest tax renewal page (back).'),
                    KycFormSchemas::documentSection(KycDocumentType::VehicleInsurance, $dir, true, $this->documentLocked(KycDocumentType::VehicleInsurance), [
                        TextInput::make('docs.vehicle_insurance.issuing_authority')
                            ->label('Insurance company')
                            ->required()
                            ->maxLength(100)
                            ->disabled($this->documentLocked(KycDocumentType::VehicleInsurance)),
                    ], 'Third-party insurance is mandatory in Nepal.'),
                ]),

            Step::make('Hub & payout')->schema([
                Select::make('home_warehouse_id')
                    ->label('Home hub (dark store you will deliver from)')
                    ->options(fn () => Warehouse::where('status', 'active')
                        ->whereHas('cities', fn ($q) => $q->whereKey($this->rider()->city_id))
                        ->pluck('name', 'id'))
                    ->required()
                    ->disabled($locked),
                TextInput::make('base_latitude')->label('Base latitude')->numeric()->minValue(26.3)->maxValue(30.5)->required()->disabled($locked),
                TextInput::make('base_longitude')->label('Base longitude')->numeric()->minValue(80.0)->maxValue(88.3)->required()->disabled($locked),
                ...KycFormSchemas::payoutFields($locked),
                KycFormSchemas::documentSection(KycDocumentType::BankProof, $dir, true, $this->documentLocked(KycDocumentType::BankProof))
                    ->visible(fn (Get $get) => in_array($get('payout_method'), [PayoutMethod::Bank, PayoutMethod::Bank->value], true)),
            ]),
        ];
    }
}
