<?php

namespace Tests\Feature\Storefront;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerSignupLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_footer_links_to_rider_and_vendor_signup_pages_that_load(): void
    {
        $this->get(route('storefront.contact'))
            ->assertOk()
            ->assertSee(url('/rider/register'), false)
            ->assertSee(url('/vendor/register'), false);

        $this->get('/rider/register')->assertOk();
        $this->get('/vendor/register')->assertOk();
    }

    public function test_privacy_contact_email_uses_the_configured_app_domain(): void
    {
        config(['app.url' => 'https://www.shop.example.com']);

        $this->get(route('storefront.privacy'))
            ->assertOk()
            ->assertSee('mailto:privacy@shop.example.com', false)
            ->assertDontSee('privacy@darkstore.np');
    }
}
