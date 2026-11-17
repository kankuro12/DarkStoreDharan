<?php

namespace Tests\Feature\Kyc;

use App\Enums\LocationChangeStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Exceptions\VerificationException;
use App\Filament\Resources\LocationChangeRequests\Pages\ManageLocationChangeRequests;
use App\Filament\Rider\Pages\RequestLocationChange;
use App\Models\AuditLog;
use App\Models\City;
use App\Models\DeliveryAgent;
use App\Models\Warehouse;
use App\Services\Sms\SmsService;
use App\Services\Verification\LocationChangeService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesOnboardingFixtures;
use Tests\TestCase;

class LocationChangeTest extends TestCase
{
    use CreatesOnboardingFixtures, RefreshDatabase;

    private LocationChangeService $service;

    private City $newCity;

    private Warehouse $newHub;

    private DeliveryAgent $rider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(SmsService::class)->shouldReceive('send')->andReturnTrue();
        $this->service = app(LocationChangeService::class);

        $city = $this->makeCity('Dharan');
        $hub = $this->makeWarehouse($city, 'WH-DHR');
        $this->newCity = $this->makeCity('Itahari');
        $this->newHub = $this->makeWarehouse($this->newCity, 'WH-ITH');
        $this->rider = $this->makeRider([
            'city_id' => $city->id, 'home_warehouse_id' => $hub->id, 'base_latitude' => 26.81, 'base_longitude' => 87.28,
            'verification_status' => VerificationStatus::Approved, 'training_completed_at' => now(),
        ]);
    }

    private function proposal(array $overrides = []): array
    {
        return array_merge([
            'city_id' => $this->newCity->id,
            'home_warehouse_id' => $this->newHub->id,
            'base_latitude' => 26.66,
            'base_longitude' => 87.27,
        ], $overrides);
    }

    public function test_approved_rider_can_request_change_and_second_pending_request_is_refused(): void
    {
        $request = $this->service->request($this->rider, $this->proposal(), 'Moved to Itahari');

        $this->assertSame(LocationChangeStatus::Pending, $request->status);
        $this->assertEquals($this->rider->city_id, $request->current['city_id']);
        $this->assertTrue(AuditLog::where('action', 'location_change_requested')->exists());

        $this->expectException(VerificationException::class);
        $this->service->request($this->rider, $this->proposal(), 'Again');
    }

    public function test_unapproved_rider_cannot_request_change(): void
    {
        $this->rider->update(['verification_status' => VerificationStatus::Submitted]);

        $this->expectException(VerificationException::class);
        $this->service->request($this->rider, $this->proposal(), 'Moved');
    }

    public function test_new_hub_must_serve_new_city(): void
    {
        $this->expectException(VerificationException::class);
        $this->service->request($this->rider, $this->proposal(['city_id' => $this->rider->city_id]), 'Wrong hub');
    }

    public function test_only_location_fields_can_be_proposed(): void
    {
        $request = $this->service->request($this->rider, $this->proposal(['verification_status' => 'approved', 'name' => 'X']), 'Moved');

        $this->assertArrayNotHasKey('name', $request->proposed);
        $this->assertArrayNotHasKey('verification_status', $request->proposed);
    }

    public function test_admin_approval_applies_location_and_audits(): void
    {
        $admin = $this->makeStaff(UserRole::DeliveryCoordinator);
        $request = $this->service->request($this->rider, $this->proposal(), 'Moved');

        $this->service->approve($request, $admin, 'Confirmed by phone');

        $rider = $this->rider->fresh();
        $this->assertSame($this->newCity->id, $rider->city_id);
        $this->assertSame($this->newHub->id, $rider->home_warehouse_id);
        $this->assertSame(LocationChangeStatus::Approved, $request->fresh()->status);
        $this->assertSame($admin->id, $request->fresh()->reviewed_by);
        $this->assertTrue(AuditLog::where('action', 'location_change_approved')->where('user_id', $admin->id)->exists());
    }

    public function test_rejection_leaves_location_untouched(): void
    {
        $admin = $this->makeStaff(UserRole::DeliveryCoordinator);
        $originalCity = $this->rider->city_id;
        $request = $this->service->request($this->rider, $this->proposal(), 'Moved');

        $this->service->reject($request, $admin, 'No riders needed in Itahari now');

        $this->assertSame($originalCity, $this->rider->fresh()->city_id);
        $this->assertSame(LocationChangeStatus::Rejected, $request->fresh()->status);
    }

    public function test_rider_submits_request_from_panel_page(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('rider'));
        $this->actingAs($this->rider, 'delivery');

        Livewire::test(RequestLocationChange::class)
            ->fillForm(array_merge($this->proposal(), ['reason' => 'Family moved']))
            ->call('submit')
            ->assertHasNoFormErrors();

        $this->assertSame(1, $this->rider->locationChangeRequests()->count());
    }

    public function test_unapproved_rider_cannot_open_request_page(): void
    {
        $this->rider->update(['verification_status' => VerificationStatus::NeedsChanges]);

        $this->actingAs($this->rider, 'delivery')->get(RequestLocationChange::getUrl(panel: 'rider'))->assertRedirect();
    }

    public function test_coordinator_approves_from_admin_list(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $request = $this->service->request($this->rider, $this->proposal(), 'Moved');

        $this->actingAs($this->makeStaff(UserRole::DeliveryCoordinator));
        Livewire::test(ManageLocationChangeRequests::class)
            ->assertCanSeeTableRecords([$request])
            ->callTableAction('approve', $request, data: ['note' => 'ok']);

        $this->assertSame($this->newCity->id, $this->rider->fresh()->city_id);
    }

    public function test_catalog_manager_cannot_see_rider_requests(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $request = $this->service->request($this->rider, $this->proposal(), 'Moved');

        $this->actingAs($this->makeStaff(UserRole::CatalogManager));
        Livewire::test(ManageLocationChangeRequests::class, ['activeTab' => 'all'])
            ->assertCanNotSeeTableRecords([$request]);
    }
}
