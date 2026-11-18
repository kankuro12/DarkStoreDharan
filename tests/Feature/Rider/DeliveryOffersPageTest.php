<?php

namespace Tests\Feature\Rider;

use App\Enums\DeliveryStatus;
use App\Filament\Rider\Pages\DeliveryOffers;
use App\Filament\Rider\Pages\MyDeliveries;
use App\Services\Delivery\DeliveryOfferService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DeliveryOffersPageTest extends TestCase
{
    use DeliveryOfferFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOfferFixtures();
        Filament::setCurrentPanel(Filament::getPanel('rider'));
    }

    public function test_offers_page_lists_offer_with_pay_and_area_but_no_customer_details(): void
    {
        $rider = $this->makeRider();
        $this->makeOpenOffer(['address_id' => $this->makeAddress()->id]);

        $this->actingAs($rider, 'delivery')
            ->get(DeliveryOffers::getUrl())
            ->assertOk()
            ->assertSee('Putali Line')
            ->assertSee('Rs 50')
            ->assertDontSee('Sita Sharma')
            ->assertDontSee('9800000001')
            ->assertDontSee('Gali 4');
    }

    public function test_rider_accepts_offer_from_page(): void
    {
        $rider = $this->makeRider();
        $order = $this->makeOpenOffer();

        Livewire::actingAs($rider, 'delivery')
            ->test(DeliveryOffers::class)
            ->call('acceptOffer', $order->id)
            ->assertRedirect(MyDeliveries::getUrl());

        $this->assertSame($rider->id, $order->fresh()->delivery_agent_id);
    }

    public function test_accepting_a_taken_offer_shows_warning_and_keeps_owner(): void
    {
        $winner = $this->makeRider();
        $loser = $this->makeRider();
        $order = $this->makeOpenOffer();

        $page = Livewire::actingAs($loser, 'delivery')->test(DeliveryOffers::class);
        app(DeliveryOfferService::class)->accept($order, $winner);

        $page->call('acceptOffer', $order->id)
            ->assertNoRedirect()
            ->assertNotified('Already taken or not available');

        $this->assertSame($winner->id, $order->fresh()->delivery_agent_id);
    }

    public function test_decline_removes_offer_from_page(): void
    {
        $rider = $this->makeRider();
        $order = $this->makeOpenOffer();

        Livewire::actingAs($rider, 'delivery')
            ->test(DeliveryOffers::class)
            ->assertSee($order->warehouse->name)
            ->call('declineOffer', $order->id)
            ->assertSee('No offers right now.');
    }

    public function test_toggle_online_and_blocked_offline_with_assigned_order(): void
    {
        $rider = $this->makeRider(['availability' => 'offline']);

        $page = Livewire::actingAs($rider, 'delivery')
            ->test(DeliveryOffers::class)
            ->assertSee('Go online to see delivery offers')
            ->call('toggleOnline')
            ->assertSee('You are online');
        $this->assertSame('available', $rider->fresh()->availability);

        app(DeliveryOfferService::class)->accept($this->makeOpenOffer(), $rider->fresh());

        $page->call('toggleOnline')
            ->assertNotified('Finish or hand back your current deliveries before going offline');
        $this->assertSame('available', $rider->fresh()->availability);
    }

    public function test_unapproved_rider_cannot_open_offers_page(): void
    {
        $rider = $this->makeRider(['verification_status' => 'under_review']);

        $this->actingAs($rider, 'delivery')
            ->get(DeliveryOffers::getUrl())
            ->assertRedirect();
    }

    public function test_my_deliveries_shows_earning(): void
    {
        $rider = $this->makeRider();
        $this->makeOfferOrder([
            'delivery_agent_id' => $rider->id,
            'delivery_status' => DeliveryStatus::Assigned,
            'rider_pay' => 70,
        ]);

        $this->actingAs($rider, 'delivery')
            ->get(MyDeliveries::getUrl())
            ->assertOk()
            ->assertSee('Earning Rs 70');
    }
}
