<?php

namespace Tests\Feature\Rider;

use App\Enums\DeliveryStatus;
use App\Enums\KycDocumentStatus;
use App\Enums\KycDocumentType;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Filament\Resources\DeliveryAgents\DeliveryAgentResource;
use App\Filament\Resources\DeliveryAgents\Pages\ManageDeliveryAgents;
use App\Filament\Resources\DeliveryAgents\Pages\ViewDeliveryAgent;
use App\Filament\Shared\KycDocumentsRelationManager;
use App\Models\AuditLog;
use App\Models\DeliveryAgent;
use App\Models\User;
use App\Services\Sms\SmsService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesOnboardingFixtures;
use Tests\TestCase;

class AdminRiderManagementTest extends TestCase
{
    use CreatesOnboardingFixtures, RefreshDatabase;

    private User $coordinator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(SmsService::class)->shouldReceive('send')->andReturnTrue();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->coordinator = $this->makeStaff(UserRole::DeliveryCoordinator);
    }

    private function submittedRider(): DeliveryAgent
    {
        $rider = $this->makeRider(['vehicle_type' => 'bicycle', 'verification_status' => VerificationStatus::Submitted]);
        $rider->upsertKycDocument(KycDocumentType::ProfilePhoto, ['file_front' => 'kyc/p.jpg']);
        $rider->upsertKycDocument(KycDocumentType::NationalId, ['file_front' => 'kyc/f.jpg', 'file_back' => 'kyc/b.jpg', 'document_number' => '1234567890']);

        return $rider;
    }

    public function test_delivery_coordinator_can_list_riders_warehouse_manager_and_catalog_manager_cannot(): void
    {
        $this->actingAs($this->coordinator)->get(DeliveryAgentResource::getUrl('index'))->assertOk();

        foreach ([UserRole::WarehouseManager, UserRole::CatalogManager] as $role) {
            $this->flushSession(); // AuthenticateSession pins the session to the first user's password hash
            $this->actingAs($this->makeStaff($role))->get(DeliveryAgentResource::getUrl('index'))->assertForbidden();
        }
    }

    public function test_view_page_renders_rider_details_and_documents(): void
    {
        $rider = $this->submittedRider();

        $this->actingAs($this->coordinator)
            ->get(DeliveryAgentResource::getUrl('view', ['record' => $rider]))
            ->assertOk()
            ->assertSee($rider->name)
            ->assertSee('National ID')
            ->assertDontSee('1234567890'); // numbers stay masked until revealed
    }

    public function test_pending_review_tab_lists_submitted_riders_only(): void
    {
        $submitted = $this->submittedRider();
        $draft = $this->makeRider();
        $this->actingAs($this->coordinator);

        Livewire::test(ManageDeliveryAgents::class, ['activeTab' => 'pending_review'])
            ->assertCanSeeTableRecords([$submitted])
            ->assertCanNotSeeTableRecords([$draft]);
    }

    public function test_admin_approves_documents_then_rider(): void
    {
        $rider = $this->submittedRider();
        $this->actingAs($this->coordinator);

        Livewire::test(ViewDeliveryAgent::class, ['record' => $rider->getKey()])->callAction('startReview');

        foreach ($rider->kycDocuments as $doc) {
            Livewire::test(KycDocumentsRelationManager::class, ['ownerRecord' => $rider->fresh(), 'pageClass' => ViewDeliveryAgent::class])
                ->callTableAction('approve', $doc);
        }

        Livewire::test(ViewDeliveryAgent::class, ['record' => $rider->getKey()])->callAction('approve');

        $this->assertSame(VerificationStatus::Approved, $rider->fresh()->verification_status);
        $this->assertSame($this->coordinator->id, $rider->fresh()->approved_by);
    }

    public function test_reject_document_then_request_changes(): void
    {
        $rider = $this->submittedRider();
        $rider->update(['verification_status' => VerificationStatus::UnderReview]);
        $this->actingAs($this->coordinator);
        $photo = $rider->kycDocuments()->where('type', KycDocumentType::ProfilePhoto)->first();

        Livewire::test(KycDocumentsRelationManager::class, ['ownerRecord' => $rider, 'pageClass' => ViewDeliveryAgent::class])
            ->callTableAction('reject', $photo, data: ['reason' => 'Face not visible']);
        Livewire::test(ViewDeliveryAgent::class, ['record' => $rider->getKey()])
            ->callAction('requestChanges', data: ['reason' => 'Please upload a clear selfie']);

        $this->assertSame(KycDocumentStatus::Rejected, $photo->fresh()->status);
        $this->assertSame('Face not visible', $photo->fresh()->rejection_reason);
        $this->assertSame(VerificationStatus::NeedsChanges, $rider->fresh()->verification_status);
    }

    public function test_admin_cannot_approve_rider_with_pending_document(): void
    {
        $rider = $this->submittedRider();
        $rider->update(['verification_status' => VerificationStatus::UnderReview]);
        $this->actingAs($this->coordinator);

        Livewire::test(ViewDeliveryAgent::class, ['record' => $rider->getKey()])
            ->callAction('approve')
            ->assertNotified();

        $this->assertSame(VerificationStatus::UnderReview, $rider->fresh()->verification_status);
    }

    public function test_reveal_document_number_is_audited(): void
    {
        $rider = $this->submittedRider();
        $this->actingAs($this->coordinator);
        $nid = $rider->kycDocuments()->where('type', KycDocumentType::NationalId)->first();

        Livewire::test(KycDocumentsRelationManager::class, ['ownerRecord' => $rider, 'pageClass' => ViewDeliveryAgent::class])
            ->mountTableAction('revealNumber', $nid)
            ->assertMountedActionModalSee('1234567890');

        $this->assertTrue(AuditLog::where('action', 'kyc_document_number_revealed')->where('user_id', $this->coordinator->id)->exists());
    }

    public function test_suspend_releases_assigned_orders_keeps_picked_up(): void
    {
        $rider = $this->makeRider(['verification_status' => VerificationStatus::Approved, 'training_completed_at' => now()]);
        $warehouse = $this->makeWarehouse();
        $assigned = $this->makeOrderFor($rider, $warehouse, DeliveryStatus::Assigned);
        $pickedUp = $this->makeOrderFor($rider, $warehouse, DeliveryStatus::PickedUp);
        $this->actingAs($this->coordinator);

        Livewire::test(ViewDeliveryAgent::class, ['record' => $rider->getKey()])
            ->callAction('suspend', data: ['reason' => 'Customer complaints'])
            ->assertNotified();

        $this->assertSame(VerificationStatus::Suspended, $rider->fresh()->verification_status);
        $this->assertNull($assigned->fresh()->delivery_agent_id);
        $this->assertSame($rider->id, $pickedUp->fresh()->delivery_agent_id);
    }

    public function test_deactivated_rider_is_locked_out_of_panel(): void
    {
        $rider = $this->makeRider(['verification_status' => VerificationStatus::Approved, 'training_completed_at' => now()]);
        $this->actingAs($this->coordinator);

        Livewire::test(ViewDeliveryAgent::class, ['record' => $rider->getKey()])->callAction('deactivateLogin');

        $this->assertSame('inactive', $rider->fresh()->status);
        $this->actingAs($rider->fresh(), 'delivery')->get('/rider/my-deliveries')->assertForbidden();
    }

    public function test_mark_training_complete_makes_rider_dispatchable(): void
    {
        $rider = $this->makeRider(['verification_status' => VerificationStatus::Approved]);
        $this->actingAs($this->coordinator);

        Livewire::test(ViewDeliveryAgent::class, ['record' => $rider->getKey()])->callAction('markTrainingComplete');

        $this->assertTrue(DeliveryAgent::eligibleForDispatch()->whereKey($rider->id)->exists());
        $this->assertTrue(AuditLog::where('action', 'rider_training_completed')->exists());
    }

    public function test_admin_location_edit_is_audited_without_secrets(): void
    {
        $rider = $this->makeRider(['bank_account_number' => '0001112223334']);
        $hub = $this->makeWarehouse($rider->city, 'WH-NEW-02');
        $this->actingAs($this->coordinator);

        Livewire::test(ManageDeliveryAgents::class, ['activeTab' => 'all'])
            ->callTableAction('edit', $rider, data: ['home_warehouse_id' => $hub->id, 'base_latitude' => 26.82, 'bank_account_number' => '9998887776665']);

        $this->assertEquals($hub->id, $rider->fresh()->home_warehouse_id);
        $log = AuditLog::where('action', 'rider_updated_by_admin')->first();
        $this->assertNotNull($log);
        $this->assertEquals($hub->id, $log->after['home_warehouse_id']);
        $this->assertSame('[changed]', $log->after['bank_account_number']);
        $this->assertStringNotContainsString('9998887776665', json_encode($log->after));
    }
}
