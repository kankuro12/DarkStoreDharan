<?php

namespace Tests\Feature\Storefront;

use App\Models\City;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOnboardingFixtures;
use Tests\TestCase;

class BannerCarouselTest extends TestCase
{
    use CreatesOnboardingFixtures, RefreshDatabase;

    private City $city;

    protected function setUp(): void
    {
        parent::setUp();
        $this->city = $this->makeCity();
        $this->makeSellableVariant($this->city, $this->makeWarehouse($this->city));
        $this->withSession(['cart.city_id' => $this->city->id]);
    }

    public function test_multiple_banners_are_full_width_with_one_dot_each_and_auto_scroll(): void
    {
        $this->city->update(['banners' => [
            ['image_url' => '/b1.jpg', 'link_url' => '/help'],
            ['image_url' => '/b2.jpg', 'link_url' => null],
            ['image_url' => '/b3.jpg', 'link_url' => null],
        ]]);

        $html = $this->get(route('storefront.home'))->assertOk()->getContent();

        $this->assertSame(3, substr_count($html, 'aria-label="Show banner'));
        $this->assertStringContainsString('Show banner 3', $html);
        $this->assertStringContainsString('setInterval', $html);
        $this->assertStringNotContainsString('sm:w-[85%]', $html);
    }

    public function test_single_banner_has_no_dots_or_auto_scroll(): void
    {
        $this->city->update(['banners' => [['image_url' => '/b1.jpg', 'link_url' => null]]]);

        $html = $this->get(route('storefront.home'))->assertOk()->getContent();

        $this->assertStringContainsString('/b1.jpg', $html);
        $this->assertStringNotContainsString('data-banner-dot', $html);
    }
}
