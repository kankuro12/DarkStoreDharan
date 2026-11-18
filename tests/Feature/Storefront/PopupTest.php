<?php

namespace Tests\Feature\Storefront;

use App\Enums\UserRole;
use App\Filament\Resources\Popups\Pages\ManagePopups;
use App\Models\City;
use App\Models\Popup;
use App\Models\ProductVariant;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesOnboardingFixtures;
use Tests\TestCase;

class PopupTest extends TestCase
{
    use CreatesOnboardingFixtures, RefreshDatabase;

    private City $city;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->city = $this->makeCity();
        $this->variant = $this->makeSellableVariant($this->city, $this->makeWarehouse($this->city));
        $this->withSession(['cart.city_id' => $this->city->id]);
    }

    private function popup(array $attributes): Popup
    {
        return Popup::create(array_merge(['title' => 'Popup', 'placement' => 'all'], $attributes));
    }

    public function test_site_wide_popup_shows_on_home_and_product_pages(): void
    {
        $this->popup(['title' => 'Dashain Sale', 'body' => 'Up to 20% off']);

        $this->get('/')->assertOk()->assertSee('Dashain Sale')->assertSee('Up to 20% off');
        $this->get(route('storefront.product', $this->variant->product->slug))->assertOk()->assertSee('Dashain Sale');
    }

    public function test_most_specific_popup_wins(): void
    {
        $this->popup(['title' => 'Everywhere Notice']);
        $this->popup(['title' => 'Honey Offer', 'placement' => 'product', 'product_id' => $this->variant->product_id]);
        $this->popup(['title' => 'Home Welcome', 'placement' => 'home']);

        $this->get(route('storefront.product', $this->variant->product->slug))->assertSee('Honey Offer')->assertDontSee('Everywhere Notice');
        $this->get('/')->assertSee('Home Welcome')->assertDontSee('Honey Offer')->assertDontSee('Everywhere Notice');
    }

    public function test_path_popup_supports_wildcards_and_only_matches_its_page(): void
    {
        $this->popup(['title' => 'Help Tip', 'placement' => 'path', 'target_path' => '/help*']);

        $this->get(route('storefront.help'))->assertOk()->assertSee('Help Tip');
        $this->get('/')->assertDontSee('Help Tip');
    }

    public function test_inactive_scheduled_and_expired_popups_are_hidden(): void
    {
        $this->popup(['title' => 'Switched Off', 'is_active' => false]);
        $this->popup(['title' => 'Not Yet', 'starts_at' => now()->addDay()]);
        $this->popup(['title' => 'Too Late', 'ends_at' => now()->subDay()]);

        $this->get('/')->assertDontSee('Switched Off')->assertDontSee('Not Yet')->assertDontSee('Too Late');
    }

    public function test_body_is_escaped(): void
    {
        $this->popup(['title' => 'Escaped', 'body' => '<script>alert(1)</script>']);

        $this->get('/')->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }

    public function test_admin_creates_popup_and_javascript_links_are_refused(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->makeStaff(UserRole::SuperAdmin));

        Livewire::test(ManagePopups::class)
            ->callAction('create', data: ['title' => 'Bad', 'placement' => 'all', 'frequency' => 'once', 'button_label' => 'Go', 'button_url' => 'javascript:alert(1)'])
            ->assertHasActionErrors(['button_url']);

        Livewire::test(ManagePopups::class)
            ->callAction('create', data: ['title' => 'Free delivery', 'placement' => 'path', 'target_path' => '/checkout', 'frequency' => 'session', 'button_label' => 'Shop', 'button_url' => '/'])
            ->assertHasNoActionErrors();

        $this->assertSame('/checkout', Popup::firstWhere('title', 'Free delivery')->target_path);
    }
}
