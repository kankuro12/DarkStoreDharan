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

    public function test_help_page_explains_how_to_become_rider_and_supplier(): void
    {
        $this->get(route('storefront.help'))
            ->assertOk()
            ->assertSee('How to become a delivery rider')
            ->assertSee('How to supply products to us')
            ->assertSee(url('/rider/register'), false)
            ->assertSee(url('/vendor/register'), false);

        $this->get(route('storefront.contact'))->assertSee(route('storefront.help'), false);
    }

    public function test_privacy_policy_has_legal_sections_with_technical_measures_as_subsection(): void
    {
        $this->get(route('storefront.privacy'))
            ->assertOk()
            ->assertSeeInOrder(['Individual Privacy Act, 2075', '8. Security of personal data', '8.2 Technical measures', '9. Your rights', '16. Contact and grievances']);
    }
}
