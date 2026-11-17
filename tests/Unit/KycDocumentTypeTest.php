<?php

namespace Tests\Unit;

use App\Enums\BusinessType;
use App\Enums\KycDocumentType;
use App\Enums\VehicleType;
use Tests\TestCase;

class KycDocumentTypeTest extends TestCase
{
    public function test_nepal_reference_data_is_complete(): void
    {
        $this->assertCount(7, config('nepal.provinces'));
        $this->assertSame(77, collect(config('nepal.districts'))->flatten()->count());
        $this->assertEqualsCanonicalizing(config('nepal.provinces'), array_keys(config('nepal.districts')));
        $this->assertNotEmpty(config('nepal.banks'));
    }

    public function test_license_categories(): void
    {
        $this->assertSame(['A', 'K'], VehicleType::Scooter->acceptedLicenseCategories());
        $this->assertSame(['A'], VehicleType::Motorcycle->acceptedLicenseCategories());
        $this->assertFalse(VehicleType::Bicycle->isMotorized());
        $this->assertTrue(KycDocumentType::DrivingLicense->needsExpiry());
        $this->assertFalse(KycDocumentType::PanCertificate->needsBack());
        $this->assertTrue(KycDocumentType::Citizenship->needsBack());
    }

    public function test_business_type_maps_to_registration_document(): void
    {
        $this->assertSame(KycDocumentType::CompanyRegistration, BusinessType::PrivateLimited->registrationDocument());
        $this->assertSame(KycDocumentType::FirmRegistration, BusinessType::SoleProprietorship->registrationDocument());
    }
}
