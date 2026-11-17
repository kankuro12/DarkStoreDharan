<?php

namespace Tests\Feature\Vendor;

use App\Enums\KycDocumentType;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Filament\Resources\Vendors\Pages\ManageVendors;
use App\Filament\Resources\Vendors\Pages\ViewVendor;
use App\Filament\Resources\Vendors\VendorResource;
use App\Filament\Shared\KycDocumentsRelationManager;
use App\Models\AuditLog;
use App\Models\KycDocument;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Sms\SmsService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\CreatesOnboardingFixtures;
use Tests\TestCase;

class AdminVendorManagementTest extends TestCase
{
    use CreatesOnboardingFixtures, RefreshDatabase;

    private User $catalogManager;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->mock(SmsService::class)->shouldReceive('send')->andReturnTrue();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->catalogManager = $this->makeStaff(UserRole::CatalogManager);
    }

    private function submittedVendor(): Vendor
    {
        $vendor = $this->makeVendor(['verification_status' => VerificationStatus::Submitted]);
        foreach ([KycDocumentType::PanCertificate, KycDocumentType::CompanyRegistration, KycDocumentType::NationalId, KycDocumentType::BankProof] as $type) {
            $vendor->upsertKycDocument($type, ['file_front' => "kyc/{$type->value}.jpg", 'document_number' => '1234567890']);
        }

        return $vendor;
    }

    public function test_catalog_manager_manages_vendors_delivery_coordinator_cannot(): void
    {
        $this->actingAs($this->catalogManager)->get(VendorResource::getUrl('index'))->assertOk();

        $this->flushSession();
        $this->actingAs($this->makeStaff(UserRole::DeliveryCoordinator))->get(VendorResource::getUrl('index'))->assertForbidden();
    }

    public function test_view_page_renders_business_details_and_documents(): void
    {
        $vendor = $this->submittedVendor();

        $this->actingAs($this->catalogManager)
            ->get(VendorResource::getUrl('view', ['record' => $vendor]))
            ->assertOk()
            ->assertSee($vendor->legal_name)
            ->assertSee($vendor->pan_number)
            ->assertSee('PAN Certificate');
    }

    public function test_admin_approval_flow_for_vendor(): void
    {
        $vendor = $this->submittedVendor();
        $this->actingAs($this->catalogManager);

        Livewire::test(ViewVendor::class, ['record' => $vendor->getKey()])->callAction('startReview');
        foreach ($vendor->kycDocuments as $doc) {
            Livewire::test(KycDocumentsRelationManager::class, ['ownerRecord' => $vendor->fresh(), 'pageClass' => ViewVendor::class])
                ->callTableAction('approve', $doc);
        }
        Livewire::test(ViewVendor::class, ['record' => $vendor->getKey()])->callAction('approve');

        $this->assertSame(VerificationStatus::Approved, $vendor->fresh()->verification_status);
        $this->assertSame($this->catalogManager->id, $vendor->fresh()->approved_by);
    }

    public function test_admin_creates_vendor_directly_as_draft(): void
    {
        $this->actingAs($this->catalogManager);
        $cityId = $this->makeCity()->id;

        Livewire::test(ManageVendors::class)
            ->callAction('create', data: [
                'legal_name' => 'Walk-in Supplier',
                'business_type' => 'sole_proprietorship',
                'pan_number' => '601234567',
                'phone' => '9812399999',
                'email' => 'walkin@vendor.test',
                'password' => 'temporary-pass',
                'city_id' => $cityId,
            ])
            ->assertHasNoActionErrors();

        $vendor = Vendor::where('pan_number', '601234567')->first();
        $this->assertSame(VerificationStatus::Draft, $vendor->verification_status);
    }

    public function test_admin_uploaded_documents_are_attributed_to_admin(): void
    {
        $vendor = $this->makeVendor();
        $this->actingAs($this->catalogManager);

        Livewire::test(KycDocumentsRelationManager::class, ['ownerRecord' => $vendor, 'pageClass' => ViewVendor::class])
            ->callTableAction('uploadDocument', data: [
                'type' => KycDocumentType::PanCertificate->value,
                'file_front' => [UploadedFile::fake()->image('pan.jpg')],
                'document_number' => $vendor->pan_number,
            ])
            ->assertHasNoTableActionErrors();

        $doc = KycDocument::first();
        $this->assertSame(KycDocumentType::PanCertificate, $doc->type);
        Storage::disk('local')->assertExists($doc->file_front);
        $this->assertSame($this->catalogManager->id, AuditLog::where('action', 'kyc_document_uploaded')->value('user_id'));
    }

    public function test_toggling_vat_in_edit_adds_vat_requirement_and_is_audited(): void
    {
        $vendor = $this->makeVendor(['bank_account_number' => '111222333444']);
        $this->actingAs($this->catalogManager);

        Livewire::test(ManageVendors::class, ['activeTab' => 'all'])
            ->callTableAction('edit', $vendor, data: ['vat_registered' => true]);

        $this->assertContains([KycDocumentType::VatCertificate], $vendor->fresh()->missingDocumentGroups());
        $this->assertSame('111222333444', $vendor->fresh()->bank_account_number); // untouched secret survives the edit
        $log = AuditLog::where('action', 'vendor_updated_by_admin')->first();
        $this->assertNotNull($log);
        $this->assertArrayNotHasKey('bank_account_number', $log->after);
    }

    public function test_block_login_locks_vendor_out(): void
    {
        $vendor = $this->makeVendor(['verification_status' => VerificationStatus::Approved]);
        $this->actingAs($this->catalogManager);

        Livewire::test(ViewVendor::class, ['record' => $vendor->getKey()])->callAction('deactivateLogin');

        $this->assertSame('inactive', $vendor->fresh()->status);
        $this->assertTrue(AuditLog::where('action', 'vendor_login_blocked')->exists());
        $this->actingAs($vendor->fresh(), 'vendor')->get('/vendor')->assertForbidden();
    }
}
