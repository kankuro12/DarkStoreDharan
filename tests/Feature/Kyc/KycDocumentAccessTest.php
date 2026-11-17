<?php

namespace Tests\Feature\Kyc;

use App\Enums\KycDocumentType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\KycDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesOnboardingFixtures;
use Tests\TestCase;

class KycDocumentAccessTest extends TestCase
{
    use CreatesOnboardingFixtures, RefreshDatabase;

    private KycDocument $riderDoc;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::disk('local')->put('kyc/delivery_agent/1/citizenship-front.jpg', 'front-bytes');

        $this->riderDoc = $this->makeRider()->upsertKycDocument(KycDocumentType::Citizenship, [
            'file_front' => 'kyc/delivery_agent/1/citizenship-front.jpg',
            'document_number' => '12-01-74-01234',
        ]);
    }

    private function url(string $side = 'front'): string
    {
        return route('admin.kyc.file', ['document' => $this->riderDoc, 'side' => $side]);
    }

    public function test_guest_is_redirected(): void
    {
        $this->get($this->url())->assertRedirect();
    }

    public function test_customer_and_rider_sessions_cannot_download(): void
    {
        $customer = Customer::create(['name' => 'C', 'email' => 'c@test.np', 'password' => 'secret123', 'phone' => '9841000000']);
        $this->actingAs($customer, 'customer')->get($this->url())->assertRedirect();

        auth('customer')->logout();
        $rider = $this->makeRider();
        $this->actingAs($rider, 'delivery')->get($this->url())->assertRedirect();
    }

    public function test_warehouse_manager_and_picker_get_403(): void
    {
        foreach ([UserRole::WarehouseManager, UserRole::PickerPacker, UserRole::Finance] as $role) {
            $this->actingAs($this->makeStaff($role), 'web')->get($this->url())->assertForbidden();
        }
    }

    public function test_delivery_coordinator_can_view_rider_doc(): void
    {
        $this->actingAs($this->makeStaff(UserRole::DeliveryCoordinator), 'web')
            ->get($this->url())
            ->assertOk();
    }

    public function test_super_admin_download_streams_file_and_is_audited(): void
    {
        $admin = $this->makeStaff(UserRole::SuperAdmin);

        $response = $this->actingAs($admin, 'web')->get($this->url());

        $response->assertOk();
        $this->assertSame('front-bytes', $response->streamedContent());
        $this->assertTrue(AuditLog::where('action', 'kyc_document_viewed')->where('user_id', $admin->id)->exists());
    }

    public function test_missing_back_side_is_404(): void
    {
        $this->actingAs($this->makeStaff(UserRole::SuperAdmin), 'web')->get($this->url('back'))->assertNotFound();
    }
}
