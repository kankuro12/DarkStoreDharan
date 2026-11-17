<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum KycDocumentType: string implements HasLabel
{
    case ProfilePhoto = 'profile_photo';
    case Citizenship = 'citizenship';
    case NationalId = 'national_id';
    case DrivingLicense = 'driving_license';
    case VehicleBluebook = 'vehicle_bluebook';
    case VehicleInsurance = 'vehicle_insurance';
    case PoliceClearance = 'police_clearance';
    case PanCertificate = 'pan_certificate';
    case CompanyRegistration = 'company_registration';
    case FirmRegistration = 'firm_registration';
    case VatCertificate = 'vat_certificate';
    case TaxClearance = 'tax_clearance';
    case FoodLicense = 'food_license';
    case BankProof = 'bank_proof';

    public function needsBack(): bool
    {
        return in_array($this, [self::Citizenship, self::NationalId, self::DrivingLicense, self::VehicleBluebook], true);
    }

    public function needsNumber(): bool
    {
        return in_array($this, [
            self::Citizenship, self::NationalId, self::DrivingLicense, self::VehicleInsurance, self::PoliceClearance,
            self::PanCertificate, self::CompanyRegistration, self::FirmRegistration, self::FoodLicense,
        ], true);
    }

    public function needsExpiry(): bool
    {
        return in_array($this, [self::DrivingLicense, self::VehicleBluebook, self::VehicleInsurance, self::TaxClearance, self::FoodLicense], true);
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return match ($this) {
            self::ProfilePhoto => 'Profile Photo (Selfie)',
            self::Citizenship => 'Citizenship Certificate (Nagarikta)',
            self::NationalId => 'National ID (Rastriya Parichaya Patra)',
            self::DrivingLicense => 'Driving License',
            self::VehicleBluebook => 'Vehicle Bluebook (Billbook)',
            self::VehicleInsurance => 'Third-Party Vehicle Insurance',
            self::PoliceClearance => 'Police Clearance (Character Certificate)',
            self::PanCertificate => 'PAN Certificate (IRD)',
            self::CompanyRegistration => 'Company Registration (OCR)',
            self::FirmRegistration => 'Firm Registration Certificate',
            self::VatCertificate => 'VAT Registration Certificate',
            self::TaxClearance => 'Tax Clearance Certificate',
            self::FoodLicense => 'Food License (DFTQC)',
            self::BankProof => 'Bank Proof (Cancelled Cheque / Bank Letter)',
        };
    }
}
