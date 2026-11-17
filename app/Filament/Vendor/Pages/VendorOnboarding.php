<?php

namespace App\Filament\Vendor\Pages;

use App\Enums\BusinessType;
use App\Enums\KycDocumentType;
use App\Filament\Shared\KycFormSchemas;
use App\Filament\Shared\KycOnboardingPage;
use App\Models\Vendor;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class VendorOnboarding extends KycOnboardingPage
{
    protected static ?string $title = 'Supplier verification';

    protected static ?string $navigationLabel = 'Verification';

    protected static ?string $slug = 'onboarding';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    public static function shouldRegisterNavigation(): bool
    {
        return ! auth('vendor')->user()?->isApproved();
    }

    protected function party(): Model
    {
        return auth('vendor')->user();
    }

    private function vendor(): Vendor
    {
        return $this->party();
    }

    protected function profileFields(): array
    {
        return [
            'legal_name', 'trade_name', 'business_type', 'registration_number', 'registration_authority',
            'pan_number', 'vat_registered', 'sells_food', 'authorized_person_name', 'authorized_person_designation',
            'pickup_province', 'pickup_district', 'pickup_municipality', 'pickup_ward', 'pickup_tole',
            'pickup_latitude', 'pickup_longitude',
            'payout_method', 'bank_name', 'bank_account_name', 'bank_account_number', 'wallet_id',
        ];
    }

    protected function metaFields(): array
    {
        return ['fiscal_year'];
    }

    protected function agreementText(): string
    {
        return config('kyc.vendor_agreement');
    }

    protected function statusPageUrl(): string
    {
        return VendorAccountStatus::getUrl();
    }

    public function mount(): void
    {
        parent::mount();
        $this->data['registration_number'] = $this->vendor()->registration_number;
    }

    /** PAN and registration numbers are entered once as business details and copied onto their documents. */
    protected function prepareState(array $state): array
    {
        $vendor = $this->vendor();
        $pan = $state['pan_number'] ?? $vendor->pan_number;
        $registration = $state['registration_number'] ?? $vendor->registration_number;

        if (isset($state['docs']['pan_certificate'])) {
            $state['docs']['pan_certificate']['document_number'] = $pan;
        }

        foreach (['company_registration', 'firm_registration'] as $type) {
            if (isset($state['docs'][$type])) {
                $state['docs'][$type]['document_number'] = $registration;
                $state['docs'][$type]['issuing_authority'] = $state['registration_authority'] ?? $vendor->registration_authority;
            }
        }

        return $state;
    }

    protected function steps(): array
    {
        $locked = $this->profileLocked();
        $required = $this->requiredWhileEditable();
        $dir = $this->documentDirectory();
        $registrationDoc = fn (Get $get) => BusinessType::tryFrom((string) ($get('business_type') instanceof BusinessType ? $get('business_type')->value : $get('business_type')))
            ?->registrationDocument();

        return [
            Step::make('Business')->schema([
                TextInput::make('legal_name')->label('Registered business name')->required($required)->maxLength(200)->disabled($locked),
                TextInput::make('trade_name')->label('Trade / brand name (internal only)')->maxLength(200)->disabled($locked),
                Select::make('business_type')->options(BusinessType::class)->required($required)->live()->disabled($locked),
                TextInput::make('registration_number')->label('Registration number')->required($required)->maxLength(60)->disabled($locked),
                TextInput::make('registration_authority')
                    ->label('Registered at')
                    ->placeholder('Office of the Company Registrar / Department of Commerce / Cottage & Small Industries office')
                    ->required($required)
                    ->maxLength(150)
                    ->disabled($locked),
                TextInput::make('pan_number')
                    ->label('PAN (9 digits)')
                    ->required($required)
                    ->regex('/^\d{9}$/')
                    ->unique(Vendor::class, 'pan_number', ignorable: fn () => $this->vendor())
                    ->disabled($locked),
                Toggle::make('vat_registered')->label('VAT registered (your VAT number is your PAN)')->live()->disabled($locked),
                Toggle::make('sells_food')->label('We supply food items (DFTQC license needed)')->live()->disabled($locked),
            ])->columns(2),

            Step::make('Business documents')->schema([
                KycFormSchemas::documentSection(KycDocumentType::PanCertificate, $dir, true, $this->documentLocked(KycDocumentType::PanCertificate), askNumber: false),
                KycFormSchemas::documentSection(KycDocumentType::CompanyRegistration, $dir, true, $this->documentLocked(KycDocumentType::CompanyRegistration), askNumber: false)
                    ->visible(fn (Get $get) => $registrationDoc($get) === KycDocumentType::CompanyRegistration),
                KycFormSchemas::documentSection(KycDocumentType::FirmRegistration, $dir, true, $this->documentLocked(KycDocumentType::FirmRegistration), askNumber: false)
                    ->visible(fn (Get $get) => $registrationDoc($get) === KycDocumentType::FirmRegistration),
                KycFormSchemas::documentSection(KycDocumentType::VatCertificate, $dir, true, $this->documentLocked(KycDocumentType::VatCertificate))
                    ->visible(fn (Get $get) => (bool) $get('vat_registered')),
                KycFormSchemas::documentSection(KycDocumentType::FoodLicense, $dir, true, $this->documentLocked(KycDocumentType::FoodLicense))
                    ->visible(fn (Get $get) => (bool) $get('sells_food')),
                KycFormSchemas::documentSection(KycDocumentType::TaxClearance, $dir, false, $this->documentLocked(KycDocumentType::TaxClearance), [
                    TextInput::make('docs.tax_clearance.fiscal_year')
                        ->label('Fiscal year (BS, e.g. 2082/83)')
                        ->regex('/^\d{4}\/\d{2}$/')
                        ->disabled($this->documentLocked(KycDocumentType::TaxClearance)),
                ], 'Optional for new businesses; required at your first renewal.')->collapsed(),
            ]),

            Step::make('Authorized person')->schema([
                TextInput::make('authorized_person_name')->label('Full name (as on citizenship)')->required($required)->maxLength(150)->disabled($locked),
                TextInput::make('authorized_person_designation')->label('Designation')->required($required)->maxLength(80)->disabled($locked),
                ...$this->identityDocumentFields('Identity document of the authorized person'),
            ]),

            Step::make('Pickup location')->schema([
                Section::make('Pickup address (fixed; changes need approval)')
                    ->schema(KycFormSchemas::addressFields('pickup', $required, $locked))
                    ->columns(2),
                TextInput::make('pickup_latitude')->numeric()->minValue(26.3)->maxValue(30.5)->required($required)->disabled($locked),
                TextInput::make('pickup_longitude')->numeric()->minValue(80.0)->maxValue(88.3)->required($required)->disabled($locked),
            ])->columns(2),

            Step::make('Payout')->schema([
                ...KycFormSchemas::payoutFields($locked),
                KycFormSchemas::documentSection(KycDocumentType::BankProof, $dir, true, $this->documentLocked(KycDocumentType::BankProof),
                    description: 'A cancelled cheque or a bank letter showing the account holder name and number.'),
            ]),
        ];
    }
}
