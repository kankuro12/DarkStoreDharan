<?php

namespace Tests\Feature\Rider;

use App\Enums\VerificationStatus;
use App\Filament\Rider\Pages\Auth\RiderRegister;
use App\Filament\Shared\PhoneOrEmailLogin;
use App\Models\Customer;
use App\Models\DeliveryAgent;
use App\Services\Auth\OtpService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesOnboardingFixtures;
use Tests\TestCase;

class RiderRegistrationTest extends TestCase
{
    use CreatesOnboardingFixtures, RefreshDatabase;

    private int $cityId;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('rider'));
        $this->cityId = $this->makeCity()->id;
    }

    private function otpVerifies(bool $result): void
    {
        $this->mock(OtpService::class)->shouldReceive('verify')->andReturn($result);
    }

    private function formData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Sita Rai',
            'phone' => '9812345678',
            'otp_code' => '123456',
            'email' => null,
            'password' => 'rider-secret',
            'passwordConfirmation' => 'rider-secret',
            'city_id' => $this->cityId,
            'vehicle_type' => 'scooter',
        ], $overrides);
    }

    public function test_rider_page_is_reachable_at_rider_register(): void
    {
        $this->get('/rider/register')->assertOk()->assertSee('Mobile number')->assertSee('Become a DarkStore rider');
    }

    public function test_rider_can_register_with_valid_otp(): void
    {
        $this->otpVerifies(true);

        Livewire::test(RiderRegister::class)
            ->fillForm($this->formData())
            ->call('register')
            ->assertHasNoFormErrors();

        $rider = DeliveryAgent::where('phone', '9812345678')->first();
        $this->assertNotNull($rider);
        $this->assertSame(VerificationStatus::Draft, $rider->verification_status);
        $this->assertSame('offline', $rider->availability);
        $this->assertNotNull($rider->phone_verified_at);
        $this->assertTrue(auth('delivery')->check());
        $this->assertFalse(auth('web')->check());
        $this->assertFalse(auth('customer')->check());
    }

    public function test_phone_is_normalized_before_saving(): void
    {
        $this->otpVerifies(true);

        Livewire::test(RiderRegister::class)
            ->fillForm($this->formData(['phone' => '+977-981-2345678']))
            ->call('register')
            ->assertHasNoFormErrors();

        $this->assertTrue(DeliveryAgent::where('phone', '9812345678')->exists());
    }

    public function test_wrong_otp_blocks_registration(): void
    {
        $this->otpVerifies(false);

        Livewire::test(RiderRegister::class)
            ->fillForm($this->formData())
            ->call('register')
            ->assertHasFormErrors(['otp_code']);

        $this->assertSame(0, DeliveryAgent::count());
    }

    public function test_duplicate_rider_phone_is_rejected(): void
    {
        $this->otpVerifies(true);
        $this->makeRider(['phone' => '9812345678']);

        Livewire::test(RiderRegister::class)
            ->fillForm($this->formData())
            ->call('register')
            ->assertHasFormErrors(['phone']);
    }

    public function test_customer_with_same_phone_can_still_become_rider(): void
    {
        $this->otpVerifies(true);
        Customer::create(['name' => 'Sita', 'email' => 'sita@test.np', 'phone' => '9812345678', 'password' => 'secret123']);

        Livewire::test(RiderRegister::class)
            ->fillForm($this->formData())
            ->call('register')
            ->assertHasNoFormErrors();
    }

    public function test_invalid_nepali_mobile_is_rejected(): void
    {
        $this->otpVerifies(true);

        Livewire::test(RiderRegister::class)
            ->fillForm($this->formData(['phone' => '1234567890']))
            ->call('register')
            ->assertHasFormErrors(['phone']);
    }

    public function test_send_otp_action_issues_rider_signup_code(): void
    {
        $this->mock(OtpService::class)->shouldReceive('issue')->once()->with('9812345678', 'rider_signup')
            ->andReturn(['success' => true, 'message' => 'Verification code sent.']);

        Livewire::test(RiderRegister::class)
            ->fillForm(['phone' => '9812345678'])
            ->callFormComponentAction('phone', 'sendOtp');
    }

    public function test_rider_can_log_in_with_phone_number(): void
    {
        $this->makeRider(['phone' => '9812345678', 'password' => 'rider-secret']);

        Livewire::test(PhoneOrEmailLogin::class)
            ->fillForm(['email' => '9812345678', 'password' => 'rider-secret'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertTrue(auth('delivery')->check());
    }
}
