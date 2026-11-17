<?php

namespace Tests\Feature\Vendor;

use App\Enums\KycDocumentType;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\Customer;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesOnboardingFixtures;
use Tests\TestCase;

class VendorGuardSeparationTest extends TestCase
{
    use CreatesOnboardingFixtures, RefreshDatabase;

    public function test_vendor_authenticates_on_its_own_guard_only(): void
    {
        $vendor = $this->makeVendor(['email' => 'v@vendor.test', 'password' => 'vendor-secret']);

        $this->assertTrue(auth('vendor')->attempt(['email' => 'v@vendor.test', 'password' => 'vendor-secret']));
        $this->assertFalse(auth('web')->attempt(['email' => 'v@vendor.test', 'password' => 'vendor-secret']));
        $this->assertFalse(auth('delivery')->attempt(['email' => 'v@vendor.test', 'password' => 'vendor-secret']));
        $this->assertFalse(auth('customer')->attempt(['email' => 'v@vendor.test', 'password' => 'vendor-secret']));
        $this->assertSame($vendor->id, auth('vendor')->id());
    }

    public function test_vendor_session_does_not_open_admin_rider_or_customer_account(): void
    {
        $vendor = $this->makeVendor(['verification_status' => VerificationStatus::Approved]);
        $this->actingAs($vendor, 'vendor');

        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/rider/my-deliveries')->assertRedirect('/rider/login');
        $this->get('/account/orders')->assertRedirect(route('login'));
    }

    public function test_staff_customer_and_rider_sessions_do_not_open_vendor_panel(): void
    {
        $this->actingAs($this->makeStaff(UserRole::SuperAdmin), 'web')->get('/vendor')->assertRedirect('/vendor/login');

        $this->flushSession();
        auth('web')->logout();
        $customer = Customer::create(['name' => 'C', 'email' => 'c@test.np', 'phone' => '9841000001', 'password' => 'secret123']);
        $this->actingAs($customer, 'customer')->get('/vendor')->assertRedirect('/vendor/login');

        $this->flushSession();
        auth('customer')->logout();
        $this->actingAs($this->makeRider(), 'delivery')->get('/vendor')->assertRedirect('/vendor/login');
    }

    public function test_vendor_login_page_is_served(): void
    {
        $this->get('/vendor/login')->assertOk();
    }

    public function test_inactive_vendor_is_denied_panel_access(): void
    {
        $vendor = $this->makeVendor(['status' => 'inactive']);

        $this->assertFalse($vendor->canAccessPanel(Filament::getPanel('vendor')));
        $this->assertTrue($this->makeVendor()->canAccessPanel(Filament::getPanel('vendor')));
        $this->assertFalse($this->makeVendor()->canAccessPanel(Filament::getPanel('rider')));
    }

    public function test_vat_and_food_flags_add_required_documents(): void
    {
        $base = $this->makeVendor();
        $flagged = $this->makeVendor(['vat_registered' => true, 'sells_food' => true]);

        $this->assertEquals([
            [KycDocumentType::PanCertificate],
            [KycDocumentType::CompanyRegistration, KycDocumentType::FirmRegistration],
            [KycDocumentType::Citizenship, KycDocumentType::NationalId],
            [KycDocumentType::BankProof],
        ], $base->requiredDocumentGroups());
        $this->assertContains([KycDocumentType::VatCertificate], $flagged->requiredDocumentGroups());
        $this->assertContains([KycDocumentType::FoodLicense], $flagged->requiredDocumentGroups());
    }

    public function test_vendor_kyc_documents_use_vendor_morph_alias_and_bank_number_is_encrypted(): void
    {
        $vendor = $this->makeVendor(['bank_account_number' => '0123456789012', 'registration_number' => '123456/079/080']);
        $vendor->upsertKycDocument(KycDocumentType::PanCertificate, ['file_front' => 'kyc/pan.jpg', 'document_number' => $vendor->pan_number]);

        $this->assertSame('vendor', DB::table('kyc_documents')->value('documentable_type'));
        $raw = DB::table('vendors')->where('id', $vendor->id)->first();
        $this->assertStringNotContainsString('0123456789012', $raw->bank_account_number);
        $this->assertStringNotContainsString('123456/079/080', $raw->registration_number);
    }
}
