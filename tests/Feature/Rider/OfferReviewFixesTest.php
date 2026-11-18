<?php

namespace Tests\Feature\Rider;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Pages\WarehouseOperations;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\User;
use App\Services\Delivery\DeliveryOfferService;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OfferReviewFixesTest extends TestCase
{
    use DeliveryOfferFixtures, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOfferFixtures();
        $this->warehouse->cities()->attach($this->city->id);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret-pass-123',
            'role' => UserRole::SuperAdmin, 'warehouse_id' => $this->warehouse->id,
        ]);
    }

    public function test_stale_manual_pick_does_not_replace_rider_who_accepted(): void
    {
        $a = $this->makeRider();
        $b = $this->makeRider();
        $order = $this->makeOpenOffer(['order_status' => OrderStatus::ReadyForDispatch]);

        $page = Livewire::actingAs($this->admin)->test(WarehouseOperations::class)
            ->set("manualRider.{$order->id}", $b->id);
        app(DeliveryOfferService::class)->accept($order, $a);

        $page->call('dispatchOrder', $order->id)->assertNotified();

        $order->refresh();
        $this->assertSame($a->id, $order->delivery_agent_id);
        $this->assertSame(OrderStatus::ReadyForDispatch, $order->order_status);
    }

    public function test_manual_assignment_loses_to_a_rider_who_accepted_after_load(): void
    {
        $order = $this->makeOpenOffer();
        $stale = $order->fresh();
        app(DeliveryOfferService::class)->accept($order, $a = $this->makeRider());

        $this->assertFalse(app(DeliveryOfferService::class)->assignManually($stale, $this->makeRider(), null));
        $this->assertSame($a->id, $order->fresh()->delivery_agent_id);
    }

    public function test_edit_modal_assignment_goes_through_offer_flow(): void
    {
        $rider = $this->makeRider();
        $order = $this->makeOpenOffer();

        Livewire::actingAs($this->admin)
            ->test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction(EditAction::class, ['delivery_agent_id' => $rider->id, 'notes' => 'ring bell'])->assertHasNoActionErrors();

        $order->refresh();
        $this->assertSame($rider->id, $order->delivery_agent_id);
        $this->assertSame(DeliveryStatus::Assigned, $order->delivery_status);
        $this->assertSame('50.00', $order->rider_pay);
        $this->assertSame('ring bell', $order->notes);
        $this->assertSame(1, $rider->openDeliveriesCount());
    }

    public function test_clearing_rider_in_edit_modal_returns_order_to_board(): void
    {
        $order = $this->makeOpenOffer();
        app(DeliveryOfferService::class)->accept($order, $this->makeRider());

        Livewire::actingAs($this->admin)
            ->test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction(EditAction::class, ['delivery_agent_id' => null]);

        $order->refresh();
        $this->assertNull($order->delivery_agent_id);
        $this->assertSame(DeliveryStatus::Pending, $order->delivery_status);
        $this->assertNull($order->rider_pay);
        $this->assertCount(1, app(DeliveryOfferService::class)->openOffersFor($this->makeRider()));
    }

    public function test_accept_works_when_city_ids_come_back_as_strings(): void
    {
        $rider = $this->makeRider();
        $rider->city_id = (string) $rider->city_id;
        $order = $this->makeOpenOffer();

        $this->assertTrue(app(DeliveryOfferService::class)->accept($order, $rider));
    }

    public function test_order_that_skips_processing_still_opens_an_offer(): void
    {
        $order = $this->makeOfferOrder(['order_status' => OrderStatus::Confirmed]);

        $order->transitionOrderStatus(OrderStatus::Packed);

        $this->assertNotNull($order->fresh()->offer_opened_at);
    }
}
