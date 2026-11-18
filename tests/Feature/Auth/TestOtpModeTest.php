<?php

namespace Tests\Feature\Auth;

use App\Services\Auth\OtpService;
use App\Services\Sms\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestOtpModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_signup_uses_fixed_code_and_sends_no_sms_when_test_otp_enabled(): void
    {
        config(['otp.test_mode' => true, 'otp.test_code' => '111222']);
        $this->mock(SmsService::class)->shouldNotReceive('send');

        $otp = app(OtpService::class);
        $this->assertTrue($otp->issue('9812300000', 'rider_signup')['success']);
        $this->assertTrue($otp->verify('9812300000', 'rider_signup', '111222'));

        $otp->issue('9812300001', 'vendor_signup');
        $this->assertFalse($otp->verify('9812300001', 'vendor_signup', '999999'));
        $this->assertTrue($otp->verify('9812300001', 'vendor_signup', '111222'));
    }

    public function test_login_and_password_reset_never_use_the_test_code(): void
    {
        config(['otp.test_mode' => true, 'otp.test_code' => '111222']);
        $this->mock(SmsService::class)->shouldReceive('send')->twice()->andReturnTrue();

        $otp = app(OtpService::class);
        $otp->issue('9812300000', 'password_reset');
        $otp->issue('9812300001', 'login');

        $this->assertFalse($otp->verify('9812300000', 'password_reset', '111222'));
        $this->assertFalse($otp->verify('9812300001', 'login', '111222'));
    }

    public function test_test_mode_is_off_by_default(): void
    {
        $this->assertFalse(config('otp.test_mode'));
        $this->mock(SmsService::class)->shouldReceive('send')->once()->andReturnTrue();

        app(OtpService::class)->issue('9812300000', 'rider_signup');

        $this->assertFalse(app(OtpService::class)->verify('9812300000', 'rider_signup', '123456'));
    }
}
