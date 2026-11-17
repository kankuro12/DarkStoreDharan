<?php

namespace Tests\Feature\Vendor;

use App\Enums\KycDocumentType;
use App\Enums\LocationChangeStatus;
use App\Enums\VerificationStatus;
use App\Filament\Vendor\Pages\Auth\VendorRegister;
use App\Filament\Vendor\Pages\RequestLocationChange;
use App\Filament\Vendor\Pages\VendorAccountStatus;
use App\Filament\Vendor\Pages\VendorOnboarding;
use App\Models\Vendor;
use App\Services\Auth\OtpService;
use App\Services\Sms\SmsService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\CreatesOnboardingFixtures;
use Tests\TestCase;

class VendorOnboardingTest extends TestCase
{
    use CreatesOnboardingFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->mock(SmsService::class)->shouldReceive('send')->andReturnTrue();
        Filament::setCurrentPanel(Filament::getPanel('vendor'));
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->image('doc.jpg');
    }

    private function vendor(array $attributes = []): Vendor
    {
        $vendor = $this->makeVendor(array_merge(['pan_number' => '600111222'], $attributes));
        $this->actingAs($vendor, 'vendor');

        return $vendor;
    }

    private function onboardingData(array $overrides = []): array
    {
        return array_merge([
            'legal_name' => 'Himalayan Traders Pvt. Ltd.',
            'trade_name' => 'Himalayan Traders',
            'business_type' => 'private_limited',
            'registration_number' => '123456/079/080',
            'registration_authority' => 'Office of the Company Registrar',
            'pan_number' => '600111222',
            'vat_registered' => false,
            'sells_food' => false,
            'docs.pan_certificate.file_front' => $this->image(),
            'docs.company_registration.file_front' => $this->image(),
            'authorized_person_name' => 'Gita Sharma',
            'authorized_person_designation' => 'Director',
            'identity_document' => 'citizenship',
            'docs.citizenship.file_front' => $this->image(),
            'docs.citizenship.file_back' => $this->image(),
            'docs.citizenship.document_number' => '12-01-74-01234',
            'docs.citizenship.issuing_authority' => 'Sunsari',
            'pickup_province' => 'Koshi Province',
            'pickup_district' => 'Sunsari',
            'pickup_municipality' => 'Dharan Sub-Metropolitan City',
            'pickup_ward' => 12,
            'pickup_tole' => 'Bhanu Chowk',
            'pickup_latitude' => 26.81,
            'pickup_longitude' => 87.28,
            'payout_method' => 'bank',
            'bank_name' => 'Nabil Bank',
            'bank_account_name' => 'Himalayan Traders Pvt. Ltd.',
            'bank_account_number' => '0101017500123',
            'docs.bank_proof.file_front' => $this->image(),
            'agreement' => true,
        ], $overrides);
    }

    public function test_vendor_registers_with_otp_and_lands_on_onboarding(): void
    {
        $this->mock(OtpService::class)->shouldReceive('verify')->with('9812300000', 'vendor_signup', '123456')->andReturnTrue();
        $cityId = $this->makeCity()->id;

        Livewire::test(VendorRegister::class)
            ->fillForm([
                'legal_name' => 'Koshi Fresh Supplies',
                'phone' => '9812300000',
                'otp_code' => '123456',
                'email' => 'koshi@vendor.test',
                'password' => 'vendor-secret',
                'passwordConfirmation' => 'vendor-secret',
                'city_id' => $cityId,
                'business_type' => 'sole_proprietorship',
                'pan_number' => '600999888',
            ])
            ->call('register')
            ->assertHasNoFormErrors();

        $vendor = Vendor::where('phone', '9812300000')->first();
        $this->assertSame(VerificationStatus::Draft, $vendor->verification_status);
        $this->assertTrue(auth('vendor')->check());
        $this->assertFalse(auth('web')->check());

        $this->get('/vendor')->assertRedirect(VendorOnboarding::getUrl());
    }

    public function test_full_onboarding_submits_for_private_limited(): void
    {
        $vendor = $this->vendor();

        Livewire::test(VendorOnboarding::class)
            ->fillForm($this->onboardingData())
            ->call('submit')
            ->assertHasNoFormErrors();

        $vendor->refresh();
        $this->assertSame(VerificationStatus::Submitted, $vendor->verification_status);
        $this->assertSame('123456/079/080', $vendor->registration_number);
        $this->assertSame('Gita Sharma', $vendor->authorized_person_name);
        $this->assertSame('600111222', $vendor->kycDocuments()->where('type', KycDocumentType::PanCertificate)->first()->document_number);
        $this->assertSame('123456/079/080', $vendor->kycDocuments()->where('type', KycDocumentType::CompanyRegistration)->first()->document_number);
        Storage::disk('local')->assertExists($vendor->kycDocuments()->first()->file_front);
    }

    public function test_private_limited_requires_company_registration_sole_prop_requires_firm_registration(): void
    {
        $this->vendor();

        Livewire::test(VendorOnboarding::class)
            ->fillForm($this->onboardingData(['docs.company_registration.file_front' => null]))
            ->call('submit')
            ->assertHasFormErrors(['docs.company_registration.file_front']);

        Livewire::test(VendorOnboarding::class)
            ->fillForm($this->onboardingData(['business_type' => 'sole_proprietorship']))
            ->call('submit')
            ->assertHasFormErrors(['docs.firm_registration.file_front']);
    }

    public function test_vat_and_food_flags_require_their_documents(): void
    {
        $this->vendor();

        Livewire::test(VendorOnboarding::class)
            ->fillForm($this->onboardingData(['vat_registered' => true, 'sells_food' => true]))
            ->call('submit')
            ->assertHasFormErrors(['docs.vat_certificate.file_front', 'docs.food_license.file_front']);
    }

    public function test_duplicate_or_malformed_pan_is_rejected(): void
    {
        $this->makeVendor(['pan_number' => '600555444']);
        $this->vendor();

        Livewire::test(VendorOnboarding::class)
            ->fillForm($this->onboardingData(['pan_number' => '12345678']))
            ->call('submit')
            ->assertHasFormErrors(['pan_number']);

        Livewire::test(VendorOnboarding::class)
            ->fillForm($this->onboardingData(['pan_number' => '600555444']))
            ->call('submit')
            ->assertHasFormErrors(['pan_number']);
    }

    public function test_tax_clearance_fiscal_year_format_validated(): void
    {
        $this->vendor();

        Livewire::test(VendorOnboarding::class)
            ->fillForm($this->onboardingData([
                'docs.tax_clearance.file_front' => $this->image(),
                'docs.tax_clearance.fiscal_year' => '2082-83',
                'docs.tax_clearance.expires_on' => now()->addMonths(6)->toDateString(),
            ]))
            ->call('submit')
            ->assertHasFormErrors(['docs.tax_clearance.fiscal_year']);
    }

    public function test_unapproved_vendor_is_confined_to_onboarding_and_status(): void
    {
        $this->vendor(['verification_status' => VerificationStatus::Submitted]);

        $this->get('/vendor')->assertRedirect(VendorAccountStatus::getUrl());
        $this->get(VendorAccountStatus::getUrl())->assertOk()->assertSee('Submitted');
        $this->get(VendorOnboarding::getUrl())->assertOk();
    }

    public function test_submitted_vendor_cannot_edit(): void
    {
        $vendor = $this->vendor();
        Livewire::test(VendorOnboarding::class)->fillForm($this->onboardingData())->call('submit');

        Livewire::test(VendorOnboarding::class)
            ->fillForm(['legal_name' => 'Someone Else Pvt. Ltd.', 'pan_number' => '600000001', 'agreement' => true])
            ->call('submit');

        $this->assertSame('Himalayan Traders Pvt. Ltd.', $vendor->fresh()->legal_name);
        $this->assertSame('600111222', $vendor->fresh()->pan_number);
    }

    public function test_approved_vendor_can_request_pickup_location_change(): void
    {
        $vendor = $this->vendor(['verification_status' => VerificationStatus::Approved]);

        Livewire::test(RequestLocationChange::class)
            ->fillForm([
                'city_id' => $vendor->city_id,
                'pickup_province' => 'Koshi Province',
                'pickup_district' => 'Morang',
                'pickup_municipality' => 'Biratnagar Metropolitan City',
                'pickup_ward' => 4,
                'pickup_tole' => 'Traffic Chowk',
                'pickup_latitude' => 26.45,
                'pickup_longitude' => 87.27,
                'reason' => 'Moved our warehouse',
            ])
            ->call('submit')
            ->assertHasNoFormErrors();

        $request = $vendor->locationChangeRequests()->first();
        $this->assertSame(LocationChangeStatus::Pending, $request->status);
        $this->assertSame('Morang', $request->proposed['pickup_district']);
    }
}
