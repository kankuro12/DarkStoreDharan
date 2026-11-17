<?php

namespace Tests\Feature\Kyc;

use App\Enums\DeliveryStatus;
use App\Enums\KycDocumentStatus;
use App\Enums\KycDocumentType;
use App\Enums\VerificationStatus;
use App\Filament\Rider\Pages\RiderAccountStatus;
use App\Filament\Rider\Pages\RiderOnboarding;
use App\Models\DeliveryAgent;
use App\Services\Sms\SmsService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\CreatesOnboardingFixtures;
use Tests\TestCase;

class KycExpiryTest extends TestCase
{
    use CreatesOnboardingFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-17 06:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** An approved scooter rider whose documents are all approved; license expiry configurable. */
    private function approvedScooterRider(string $licenseExpiry): DeliveryAgent
    {
        $rider = $this->makeRider([
            'vehicle_type' => 'scooter',
            'payout_method' => 'wallet',
            'verification_status' => VerificationStatus::Approved,
            'training_completed_at' => now(),
        ]);

        foreach ([
            [KycDocumentType::ProfilePhoto, null],
            [KycDocumentType::NationalId, null],
            [KycDocumentType::DrivingLicense, $licenseExpiry],
            [KycDocumentType::VehicleBluebook, '2027-06-01'],
            [KycDocumentType::VehicleInsurance, '2027-06-01'],
        ] as [$type, $expiry]) {
            $rider->upsertKycDocument($type, ['file_front' => "kyc/{$type->value}.jpg", 'expires_on' => $expiry])
                ->update(['status' => KycDocumentStatus::Approved]);
        }

        return $rider;
    }

    public function test_reminders_sent_once_at_30_and_7_days(): void
    {
        $sms = $this->mock(SmsService::class);
        $sms->shouldReceive('send')->withArgs(fn ($to, $message) => str_contains($message, 'expires in 30 days'))->once()->andReturnTrue();
        $sms->shouldReceive('send')->withArgs(fn ($to, $message) => str_contains($message, 'expires in 7 days'))->once()->andReturnTrue();

        $this->approvedScooterRider('2026-12-17'); // 30 days out
        $this->approvedScooterRider('2026-11-24'); // 7 days out
        $this->approvedScooterRider('2026-12-01'); // 14 days: no reminder

        $this->artisan('kyc:check-expiry')->expectsOutputToContain('reminded=2')->assertSuccessful();
        $this->artisan('kyc:check-expiry')->expectsOutputToContain('reminded=0')->assertSuccessful();
    }

    public function test_expired_license_suspends_rider_and_releases_assigned_orders(): void
    {
        $this->mock(SmsService::class)->shouldReceive('send')->andReturnTrue();
        $rider = $this->approvedScooterRider('2026-11-16');
        $assigned = $this->makeOrderFor($rider, $this->makeWarehouse(), DeliveryStatus::Assigned);

        $this->artisan('kyc:check-expiry')->expectsOutputToContain('suspended=1')->assertSuccessful();

        $rider->refresh();
        $this->assertSame(VerificationStatus::Suspended, $rider->verification_status);
        $this->assertStringStartsWith('Document expired: Driving License', $rider->rejection_reason);
        $this->assertSame(KycDocumentStatus::Expired, $rider->kycDocuments()->where('type', KycDocumentType::DrivingLicense)->first()->status);
        $this->assertNull($assigned->fresh()->delivery_agent_id);
        $this->assertFalse(DeliveryAgent::eligibleForDispatch()->whereKey($rider->id)->exists());
    }

    public function test_expired_optional_document_does_not_suspend(): void
    {
        $this->mock(SmsService::class)->shouldReceive('send')->andReturnTrue();
        $rider = $this->approvedScooterRider('2027-06-01');
        $rider->upsertKycDocument(KycDocumentType::PoliceClearance, ['file_front' => 'kyc/pc.jpg', 'expires_on' => '2026-11-01'])
            ->update(['status' => KycDocumentStatus::Approved]);

        $this->artisan('kyc:check-expiry')->expectsOutputToContain('expired=1 suspended=0')->assertSuccessful();

        $this->assertSame(VerificationStatus::Approved, $rider->fresh()->verification_status);
    }

    public function test_suspended_for_expiry_rider_can_reupload_and_resubmit(): void
    {
        $this->mock(SmsService::class)->shouldReceive('send')->andReturnTrue();
        Storage::fake('local');
        $rider = $this->approvedScooterRider('2026-11-16');
        $this->artisan('kyc:check-expiry')->assertSuccessful();

        Filament::setCurrentPanel(Filament::getPanel('rider'));
        $this->actingAs($rider->fresh(), 'delivery');

        Livewire::test(RiderAccountStatus::class)->callAction('reuploadExpired')->assertRedirect(RiderOnboarding::getUrl());
        $this->assertSame(VerificationStatus::NeedsChanges, $rider->fresh()->verification_status);

        Livewire::test(RiderOnboarding::class)
            ->fillForm([
                'docs.driving_license.file_front' => [UploadedFile::fake()->image('new-front.jpg')],
                'docs.driving_license.file_back' => [UploadedFile::fake()->image('new-back.jpg')],
                'docs.driving_license.document_number' => '01-06-12345678',
                'docs.driving_license.license_category' => 'K',
                'docs.driving_license.expires_on' => '2031-11-16',
                'agreement' => true,
            ])
            ->call('submit')
            ->assertHasNoFormErrors();

        $license = $rider->kycDocuments()->where('type', KycDocumentType::DrivingLicense)->first();
        $this->assertSame(KycDocumentStatus::Pending, $license->status);
        $this->assertSame('2031-11-16', $license->expires_on->toDateString());
        $this->assertSame(VerificationStatus::Submitted, $rider->fresh()->verification_status);
    }

    public function test_admin_suspended_rider_has_no_self_service_reupload(): void
    {
        $this->mock(SmsService::class)->shouldReceive('send')->andReturnTrue();
        $rider = $this->approvedScooterRider('2027-06-01');
        $rider->update(['verification_status' => VerificationStatus::Suspended, 'rejection_reason' => 'Misconduct']);
        Filament::setCurrentPanel(Filament::getPanel('rider'));
        $this->actingAs($rider->fresh(), 'delivery');

        Livewire::test(RiderAccountStatus::class)->assertActionHidden('reuploadExpired');
    }
}
