<?php

namespace Tests\Feature\Rider;

use App\Enums\KycDocumentStatus;
use App\Enums\KycDocumentType;
use App\Enums\VerificationStatus;
use App\Filament\Rider\Pages\RiderAccountStatus;
use App\Filament\Rider\Pages\RiderOnboarding;
use App\Models\DeliveryAgent;
use App\Models\Warehouse;
use App\Services\Sms\SmsService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\CreatesOnboardingFixtures;
use Tests\TestCase;

class RiderOnboardingTest extends TestCase
{
    use CreatesOnboardingFixtures, RefreshDatabase;

    private Warehouse $hub;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->mock(SmsService::class)->shouldReceive('send')->andReturnTrue();
        Filament::setCurrentPanel(Filament::getPanel('rider'));
    }

    private function rider(array $attributes = []): DeliveryAgent
    {
        $city = $this->makeCity();
        $this->hub = $this->makeWarehouse($city);

        $rider = $this->makeRider(array_merge(['city_id' => $city->id, 'vehicle_type' => 'bicycle'], $attributes));
        $this->actingAs($rider, 'delivery');

        return $rider;
    }

    private function image(string $name = 'doc.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name);
    }

    private function bicycleData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Sita Rai',
            'date_of_birth' => now()->subYears(25)->toDateString(),
            'gender' => 'female',
            'perm_province' => 'Koshi Province',
            'perm_district' => 'Sunsari',
            'perm_municipality' => 'Dharan Sub-Metropolitan City',
            'perm_ward' => 8,
            'perm_tole' => 'Putali Line',
            'emergency_contact_name' => 'Hari Rai',
            'emergency_contact_relation' => 'Father',
            'emergency_contact_phone' => '9800000001',
            'docs.profile_photo.file_front' => $this->image('selfie.jpg'),
            'identity_document' => 'national_id',
            'docs.national_id.file_front' => $this->image(),
            'docs.national_id.file_back' => $this->image(),
            'docs.national_id.document_number' => '1234567890',
            'home_warehouse_id' => $this->hub->id,
            'base_latitude' => 26.81,
            'base_longitude' => 87.28,
            'payout_method' => 'wallet',
            'wallet_id' => '9812345678',
            'agreement' => true,
        ], $overrides);
    }

    private function scooterData(array $overrides = []): array
    {
        return $this->bicycleData(array_merge([
            'docs.driving_license.file_front' => $this->image(),
            'docs.driving_license.file_back' => $this->image(),
            'docs.driving_license.document_number' => '01-06-12345678',
            'docs.driving_license.license_category' => 'K',
            'docs.driving_license.expires_on' => now()->addYear()->toDateString(),
            'docs.vehicle_bluebook.file_front' => $this->image(),
            'docs.vehicle_bluebook.file_back' => $this->image(),
            'docs.vehicle_bluebook.vehicle_registration_number' => 'KO 1 PA 1234',
            'docs.vehicle_bluebook.expires_on' => now()->addMonths(6)->toDateString(),
            'docs.vehicle_insurance.file_front' => $this->image(),
            'docs.vehicle_insurance.document_number' => 'POL-778899',
            'docs.vehicle_insurance.issuing_authority' => 'Shikhar Insurance',
            'docs.vehicle_insurance.expires_on' => now()->addMonths(10)->toDateString(),
        ], $overrides));
    }

    public function test_draft_rider_is_redirected_from_deliveries_to_onboarding(): void
    {
        $this->rider();

        $this->get('/rider/my-deliveries')->assertRedirect(RiderOnboarding::getUrl());
    }

    public function test_onboarding_and_status_pages_render_for_draft_rider(): void
    {
        $this->rider(['vehicle_type' => 'scooter']);

        $this->get(RiderOnboarding::getUrl())->assertOk()->assertSee('Personal details')->assertSee('Vehicle');
        $this->get(RiderAccountStatus::getUrl())->assertOk()->assertSee('Draft');
    }

    public function test_approved_rider_reaches_my_deliveries(): void
    {
        $this->rider(['verification_status' => VerificationStatus::Approved, 'training_completed_at' => now()]);

        $this->get('/rider/my-deliveries')->assertOk();
    }

    public function test_wizard_stores_files_privately_and_submits(): void
    {
        $rider = $this->rider();

        Livewire::test(RiderOnboarding::class)
            ->fillForm($this->bicycleData())
            ->call('submit')
            ->assertHasNoFormErrors();

        $rider->refresh();
        $this->assertSame(VerificationStatus::Submitted, $rider->verification_status);
        $this->assertSame('Sunsari', $rider->perm_district);
        $this->assertSame($this->hub->id, $rider->home_warehouse_id);

        $nid = $rider->kycDocuments()->where('type', KycDocumentType::NationalId)->first();
        $this->assertSame('1234567890', $nid->document_number);
        $this->assertStringStartsWith("kyc/delivery_agent/{$rider->id}/", $nid->file_front);
        Storage::disk('local')->assertExists($nid->file_front);
        $this->assertSame(2, $rider->kycDocuments()->count());
    }

    public function test_bicycle_rider_skips_vehicle_step(): void
    {
        $rider = $this->rider();

        Livewire::test(RiderOnboarding::class)
            ->fillForm($this->bicycleData())
            ->call('submit')
            ->assertHasNoFormErrors();

        $this->assertFalse($rider->kycDocuments()->where('type', KycDocumentType::DrivingLicense)->exists());
    }

    public function test_scooter_rider_must_provide_vehicle_documents(): void
    {
        $this->rider(['vehicle_type' => 'scooter']);

        Livewire::test(RiderOnboarding::class)
            ->fillForm($this->bicycleData())
            ->call('submit')
            ->assertHasFormErrors(['docs.driving_license.file_front', 'docs.vehicle_insurance.expires_on']);
    }

    public function test_scooter_rider_with_vehicle_documents_submits_and_meta_is_stored(): void
    {
        $rider = $this->rider(['vehicle_type' => 'scooter']);

        Livewire::test(RiderOnboarding::class)
            ->fillForm($this->scooterData())
            ->call('submit')
            ->assertHasNoFormErrors();

        $license = $rider->kycDocuments()->where('type', KycDocumentType::DrivingLicense)->first();
        $this->assertSame('K', $license->meta['license_category']);
        $bluebook = $rider->kycDocuments()->where('type', KycDocumentType::VehicleBluebook)->first();
        $this->assertSame('KO 1 PA 1234', $bluebook->meta['vehicle_registration_number']);
        $this->assertSame(VerificationStatus::Submitted, $rider->fresh()->verification_status);
    }

    public function test_motorcycle_rider_cannot_use_scooter_only_license(): void
    {
        $this->rider(['vehicle_type' => 'motorcycle']);

        Livewire::test(RiderOnboarding::class)
            ->fillForm($this->scooterData(['docs.driving_license.license_category' => 'K']))
            ->call('submit')
            ->assertHasFormErrors(['docs.driving_license.license_category']);
    }

    public function test_under_18_is_rejected(): void
    {
        $this->rider();

        Livewire::test(RiderOnboarding::class)
            ->fillForm($this->bicycleData(['date_of_birth' => now()->subYears(17)->toDateString()]))
            ->call('submit')
            ->assertHasFormErrors(['date_of_birth']);
    }

    public function test_rejects_disallowed_or_oversized_files(): void
    {
        $this->rider();

        foreach ([
            UploadedFile::fake()->create('x.exe', 10, 'application/x-msdownload'),
            UploadedFile::fake()->create('x.svg', 1, 'image/svg+xml'),
            UploadedFile::fake()->image('big.jpg')->size(6000),
        ] as $bad) {
            Livewire::test(RiderOnboarding::class)
                ->fillForm($this->bicycleData(['docs.profile_photo.file_front' => $bad]))
                ->call('submit')
                ->assertHasFormErrors(['docs.profile_photo.file_front']);
        }

        $this->assertSame(0, DeliveryAgent::first()->kycDocuments()->count());
    }

    public function test_rejects_already_expired_license(): void
    {
        $this->rider(['vehicle_type' => 'scooter']);

        Livewire::test(RiderOnboarding::class)
            ->fillForm($this->scooterData(['docs.driving_license.expires_on' => now()->toDateString()]))
            ->call('submit')
            ->assertHasFormErrors(['docs.driving_license.expires_on']);
    }

    public function test_invalid_nin_is_rejected(): void
    {
        $this->rider();

        Livewire::test(RiderOnboarding::class)
            ->fillForm($this->bicycleData(['docs.national_id.document_number' => '12345']))
            ->call('submit')
            ->assertHasFormErrors(['docs.national_id.document_number']);
    }

    public function test_submitted_rider_cannot_edit_documents(): void
    {
        $rider = $this->rider();
        Livewire::test(RiderOnboarding::class)->fillForm($this->bicycleData())->call('submit');
        $photo = $rider->kycDocuments()->where('type', KycDocumentType::ProfilePhoto)->first();

        Livewire::test(RiderOnboarding::class)
            ->fillForm(['docs.profile_photo.file_front' => [$this->image('swap.jpg')], 'name' => 'Someone Else', 'agreement' => true])
            ->call('submit');

        $this->assertSame($photo->file_front, $photo->fresh()->file_front);
        $this->assertSame('Sita Rai', $rider->fresh()->name);
        $this->assertSame(VerificationStatus::Submitted, $rider->fresh()->verification_status);
    }

    public function test_needs_changes_rider_can_replace_only_rejected_documents(): void
    {
        $rider = $this->rider();
        Livewire::test(RiderOnboarding::class)->fillForm($this->bicycleData())->call('submit');

        $photo = $rider->kycDocuments()->where('type', KycDocumentType::ProfilePhoto)->first();
        $nid = $rider->kycDocuments()->where('type', KycDocumentType::NationalId)->first();
        $photo->update(['status' => KycDocumentStatus::Rejected, 'rejection_reason' => 'Blurry']);
        $nid->update(['status' => KycDocumentStatus::Approved]);
        $rider->update(['verification_status' => VerificationStatus::NeedsChanges]);

        Livewire::test(RiderOnboarding::class)
            ->fillForm([
                'docs.profile_photo.file_front' => [$this->image('clear.jpg')], // replaces the old file, as the upload widget does
                'docs.national_id.document_number' => '9999999999',
                'name' => 'Changed Name',
                'agreement' => true, // consent is re-given on every submission
            ])
            ->call('submit')
            ->assertHasNoFormErrors();

        $this->assertNotSame($photo->file_front, $photo->fresh()->file_front);
        $this->assertSame(KycDocumentStatus::Pending, $photo->fresh()->status);
        $this->assertSame('1234567890', $nid->fresh()->document_number);
        $this->assertSame(KycDocumentStatus::Approved, $nid->fresh()->status);
        $this->assertSame('Sita Rai', $rider->fresh()->name);
        $this->assertSame(VerificationStatus::Submitted, $rider->fresh()->verification_status);
    }
}
