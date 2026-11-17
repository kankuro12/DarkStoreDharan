<?php

namespace Tests\Feature\Rider;

use App\Enums\DeliveryStatus;
use App\Enums\KycDocumentType;
use App\Enums\PayoutMethod;
use App\Enums\VehicleType;
use App\Enums\VerificationStatus;
use App\Models\DeliveryAgent;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesOnboardingFixtures;
use Tests\TestCase;

class RiderEligibilityTest extends TestCase
{
    use CreatesOnboardingFixtures, RefreshDatabase;

    public function test_existing_riders_are_migrated_as_approved(): void
    {
        $migration = require database_path('migrations/2026_11_17_000003_add_verification_columns_to_delivery_agents_table.php');
        $migration->down();
        $cityId = $this->makeCity()->id;
        DB::table('delivery_agents')->insert(['name' => 'Legacy', 'phone' => '9811111111', 'city_id' => $cityId, 'status' => 'active', 'availability' => 'available']);

        $migration->up();

        $legacy = DeliveryAgent::where('phone', '9811111111')->first();
        $this->assertSame(VerificationStatus::Approved, $legacy->verification_status);
        $this->assertNotNull($legacy->training_completed_at);
    }

    public function test_unapproved_untrained_or_inactive_rider_is_not_dispatchable(): void
    {
        $city = $this->makeCity();
        $this->makeRider(['city_id' => $city->id]); // draft
        $this->makeRider(['city_id' => $city->id, 'verification_status' => VerificationStatus::Approved]); // untrained
        $this->makeRider(['city_id' => $city->id, 'verification_status' => VerificationStatus::Approved, 'training_completed_at' => now(), 'status' => 'inactive']);
        $eligible = $this->makeRider(['city_id' => $city->id, 'verification_status' => VerificationStatus::Approved, 'training_completed_at' => now()]);

        $this->assertSame([$eligible->id], DeliveryAgent::eligibleForDispatch()->pluck('id')->all());
        $this->assertSame([$eligible->id], DeliveryAgent::available()->pluck('id')->all());
    }

    public function test_bicycle_rider_needs_no_license_documents(): void
    {
        $rider = $this->makeRider(['vehicle_type' => VehicleType::Bicycle, 'payout_method' => PayoutMethod::Wallet]);

        $this->assertEquals([
            [KycDocumentType::ProfilePhoto],
            [KycDocumentType::Citizenship, KycDocumentType::NationalId],
        ], $rider->requiredDocumentGroups());
    }

    public function test_scooter_rider_with_bank_payout_needs_license_bluebook_insurance_and_bank_proof(): void
    {
        $rider = $this->makeRider(['vehicle_type' => VehicleType::Scooter, 'payout_method' => PayoutMethod::Bank]);

        $groups = $rider->requiredDocumentGroups();

        foreach ([KycDocumentType::DrivingLicense, KycDocumentType::VehicleBluebook, KycDocumentType::VehicleInsurance, KycDocumentType::BankProof] as $type) {
            $this->assertContains([$type], $groups);
        }
    }

    public function test_inactive_rider_cannot_access_panel_but_draft_rider_can(): void
    {
        $panel = Filament::getPanel('rider');

        $this->assertTrue($this->makeRider()->canAccessPanel($panel));
        $this->assertFalse($this->makeRider(['status' => 'inactive'])->canAccessPanel($panel));
        $this->assertFalse($this->makeRider()->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_bank_account_number_is_encrypted_at_rest(): void
    {
        $rider = $this->makeRider(['bank_account_number' => '01234567890123']);

        $this->assertStringNotContainsString('01234567890123', DB::table('delivery_agents')->where('id', $rider->id)->value('bank_account_number'));
        $this->assertSame('01234567890123', $rider->fresh()->bank_account_number);
    }

    public function test_release_unstarted_deliveries_only_touches_assigned_orders(): void
    {
        $rider = $this->makeRider();
        $warehouse = $this->makeWarehouse();
        $assigned = $this->makeOrderFor($rider, $warehouse, DeliveryStatus::Assigned);
        $pickedUp = $this->makeOrderFor($rider, $warehouse, DeliveryStatus::PickedUp);

        $this->assertSame(1, $rider->releaseUnstartedDeliveries());

        $this->assertNull($assigned->fresh()->delivery_agent_id);
        $this->assertSame(DeliveryStatus::Pending, $assigned->fresh()->delivery_status);
        $this->assertSame($rider->id, $pickedUp->fresh()->delivery_agent_id);
    }
}
