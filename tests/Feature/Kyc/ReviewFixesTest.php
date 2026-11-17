<?php

namespace Tests\Feature\Kyc;

use App\Enums\DeliveryStatus;
use App\Enums\KycDocumentStatus;
use App\Enums\KycDocumentType;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Filament\Resources\DeliveryAgents\Pages\ViewDeliveryAgent;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Rider\Pages\Auth\RiderRegister;
use App\Filament\Rider\Pages\MyDeliveries;
use App\Filament\Rider\Pages\RiderAccountStatus;
use App\Filament\Rider\Pages\RiderOnboarding;
use App\Models\Customer;
use App\Models\DeliveryAgent;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Services\Auth\OtpService;
use App\Services\Sms\SmsService;
use App\Services\Verification\VerificationService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\CreatesOnboardingFixtures;
use Tests\TestCase;

/** Regression tests for the whole-branch review findings. */
class ReviewFixesTest extends TestCase
{
    use CreatesOnboardingFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(SmsService::class)->shouldReceive('send')->andReturnTrue();
    }

    // C1: a rider/vendor reset token must never reset a customer with the same email.
    public function test_reset_tokens_are_isolated_per_guard(): void
    {
        $customer = Customer::create(['name' => 'Victim', 'email' => 'shared@test.np', 'phone' => '9841000777', 'password' => 'secret123']);
        $rider = $this->makeRider(['email' => 'shared@test.np']);
        $vendor = $this->makeVendor(['email' => 'shared@test.np']);

        $riderToken = Password::broker('delivery_agents')->createToken($rider);
        $vendorToken = Password::broker('vendors')->createToken($vendor);

        $this->assertFalse(Password::broker('customers')->tokenExists($customer, $riderToken));
        $this->assertFalse(Password::broker('customers')->tokenExists($customer, $vendorToken));
        $this->assertTrue(Password::broker('delivery_agents')->tokenExists($rider, $riderToken));
    }

    // I2: no reset link for a rider without email (the reset page looks users up by email).
    public function test_password_reset_action_hidden_for_rider_without_email(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->makeStaff(UserRole::DeliveryCoordinator));
        $noEmail = $this->makeRider(['email' => null]);

        Livewire::test(ViewDeliveryAgent::class, ['record' => $noEmail->getKey()])
            ->assertActionHidden('sendPasswordReset');
    }

    // I3: a tampered upload path pointing at someone else's file is rejected.
    public function test_tampered_kyc_file_path_is_rejected(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('kyc/delivery_agent/999/other-persons-id.jpg', 'secret');
        $city = $this->makeCity();
        $rider = $this->makeRider(['city_id' => $city->id, 'vehicle_type' => 'bicycle']);
        Filament::setCurrentPanel(Filament::getPanel('rider'));
        $this->actingAs($rider, 'delivery');

        Livewire::test(RiderOnboarding::class)
            ->set('data.docs.profile_photo.file_front', ['tampered' => 'kyc/delivery_agent/999/other-persons-id.jpg'])
            ->call('submit')
            ->assertHasFormErrors(['docs.profile_photo.file_front']);

        $this->assertSame(0, $rider->kycDocuments()->count());
    }

    // I4a: blocking login hands unstarted orders back to dispatch.
    public function test_blocking_rider_login_releases_assigned_orders(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->makeStaff(UserRole::DeliveryCoordinator));
        $rider = $this->makeRider(['verification_status' => VerificationStatus::Approved, 'training_completed_at' => now()]);
        $assigned = $this->makeOrderFor($rider, $this->makeWarehouse(), DeliveryStatus::Assigned);

        Livewire::test(ViewDeliveryAgent::class, ['record' => $rider->getKey()])->callAction('deactivateLogin');

        $this->assertNull($assigned->fresh()->delivery_agent_id);
    }

    // I4b: a suspended rider can still finish orders already picked up.
    public function test_suspended_rider_can_complete_picked_up_orders(): void
    {
        $rider = $this->makeRider(['verification_status' => VerificationStatus::Approved, 'training_completed_at' => now()]);
        $pickedUp = $this->makeOrderFor($rider, $this->makeWarehouse(), DeliveryStatus::OutForDelivery);
        app(VerificationService::class)->suspend($rider, null, 'Misconduct');
        $this->actingAs($rider->fresh(), 'delivery');

        $this->get(MyDeliveries::getUrl(panel: 'rider'))->assertOk();

        Filament::setCurrentPanel(Filament::getPanel('rider'));
        Livewire::test(MyDeliveries::class)->call('markDelivered', $pickedUp->id);
        $this->assertSame(DeliveryStatus::Delivered, $pickedUp->fresh()->delivery_status);
    }

    public function test_suspended_rider_without_in_flight_orders_is_kept_out_of_deliveries(): void
    {
        $rider = $this->makeRider(['verification_status' => VerificationStatus::Suspended, 'rejection_reason' => 'Misconduct']);

        $this->actingAs($rider, 'delivery')->get(MyDeliveries::getUrl(panel: 'rider'))->assertRedirect();
    }

    // I5: a vendor product cannot go live with a base price at or below the vendor's cost.
    public function test_vendor_product_approval_requires_base_price_above_supply_price(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->makeStaff(UserRole::CatalogManager));
        $city = $this->makeCity();
        $vendor = $this->makeVendor(['city_id' => $city->id, 'verification_status' => VerificationStatus::Approved]);
        $product = Product::create(['name' => 'At Cost', 'slug' => 'at-cost', 'status' => 'pending_review', 'vendor_id' => $vendor->id]);
        $variant = $product->variants()->create(['sku' => 'COST-1', 'name' => '1pc', 'price' => 60, 'supply_price' => 60, 'status' => 'active']);
        ProductPrice::create(['product_variant_id' => $variant->id, 'city_id' => $city->id, 'price' => 90]);

        Livewire::test(ListProducts::class)->callTableAction('approveSubmission', $product)->assertNotified();
        $this->assertSame('pending_review', $product->fresh()->status);

        $variant->update(['price' => 85]);
        Livewire::test(ListProducts::class)->callTableAction('approveSubmission', $product);
        $this->assertSame('active', $product->fresh()->status);
    }

    // I6: an approved rider renews a soon-to-expire license without being suspended.
    public function test_approved_rider_can_renew_document_before_expiry(): void
    {
        Storage::fake('local');
        $rider = $this->makeRider(['vehicle_type' => 'scooter', 'verification_status' => VerificationStatus::Approved, 'training_completed_at' => now()]);
        $license = $rider->upsertKycDocument(KycDocumentType::DrivingLicense, ['file_front' => 'kyc/old.jpg', 'expires_on' => now()->addDays(20)->toDateString()]);
        $license->update(['status' => KycDocumentStatus::Approved]);
        $insurance = $rider->upsertKycDocument(KycDocumentType::VehicleInsurance, ['file_front' => 'kyc/ins.jpg', 'expires_on' => now()->addDays(200)->toDateString()]);
        $insurance->update(['status' => KycDocumentStatus::Approved]);
        Filament::setCurrentPanel(Filament::getPanel('rider'));
        $this->actingAs($rider, 'delivery');
        $this->get(RiderAccountStatus::getUrl())->assertSee('Renew documents');

        Livewire::test(RiderOnboarding::class)
            ->fillForm([
                'docs.driving_license.file_front' => [UploadedFile::fake()->image('renewed-front.jpg')],
                'docs.driving_license.file_back' => [UploadedFile::fake()->image('renewed-back.jpg')],
                'docs.driving_license.document_number' => '01-06-12345678',
                'docs.driving_license.license_category' => 'K',
                'docs.driving_license.expires_on' => now()->addYears(5)->toDateString(),
                'docs.vehicle_insurance.expires_on' => now()->addYears(9)->toDateString(), // not renewable yet: ignored
            ])
            ->call('submit')
            ->assertHasNoFormErrors();

        $this->assertSame(KycDocumentStatus::Pending, $license->fresh()->status);
        $this->assertSame(now()->addYears(5)->toDateString(), $license->fresh()->expires_on->toDateString());
        $this->assertSame(KycDocumentStatus::Approved, $insurance->fresh()->status);
        $this->assertSame(VerificationStatus::Approved, $rider->fresh()->verification_status);
        $this->assertTrue(DeliveryAgent::eligibleForDispatch()->whereKey($rider->id)->exists());
    }

    // I7: the panel "Send code" action is limited per IP, not just per phone.
    public function test_send_code_action_is_rate_limited_per_ip(): void
    {
        $this->mock(OtpService::class)->shouldReceive('issue')->times(5)->andReturn(['success' => true, 'message' => 'sent']);
        Filament::setCurrentPanel(Filament::getPanel('rider'));

        foreach (range(1, 7) as $i) {
            Livewire::test(RiderRegister::class)
                ->fillForm(['phone' => '981234567'.$i])
                ->callFormComponentAction('phone', 'sendOtp');
        }
    }
}
