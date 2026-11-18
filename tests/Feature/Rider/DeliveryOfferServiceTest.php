<?php

namespace Tests\Feature\Rider;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Order;
use App\Services\Delivery\DeliveryOfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryOfferServiceTest extends TestCase
{
    use DeliveryOfferFixtures, RefreshDatabase;

    private DeliveryOfferService $offers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOfferFixtures();
        $this->offers = app(DeliveryOfferService::class);
        $this->freezeTime();
    }

    public function test_available_rider_sees_open_offers_in_their_city_only(): void
    {
        $rider = $this->makeRider();
        $mine = $this->makeOpenOffer();
        $this->makeOpenOffer(['city_id' => $this->makeCity('itahari-offers')->id]);
        $this->makeOfferOrder(); // packing, but no offer opened (e.g. assigned earlier)

        $this->assertSame([$mine->id], $this->offers->openOffersFor($rider)->pluck('id')->all());

        $rider->update(['availability' => 'offline']);
        $this->assertCount(0, $this->offers->openOffersFor($rider->fresh()));
    }

    public function test_accept_assigns_rider_and_locks_current_pay(): void
    {
        $rider = $this->makeRider();
        $order = $this->makeOpenOffer();
        $this->travel(4)->minutes();

        $this->assertTrue($this->offers->accept($order, $rider));

        $order->refresh();
        $this->assertSame($rider->id, $order->delivery_agent_id);
        $this->assertSame(DeliveryStatus::Assigned, $order->delivery_status);
        $this->assertSame('70.00', $order->rider_pay);
        $this->assertTrue(AuditLog::where('action', 'delivery_offer_accepted')->exists());
    }

    public function test_second_rider_cannot_take_an_already_accepted_order(): void
    {
        $a = $this->makeRider();
        $b = $this->makeRider();
        $order = $this->makeOpenOffer();
        $stale = Order::find($order->id);

        $this->assertTrue($this->offers->accept(Order::find($order->id), $a));
        $this->assertFalse($this->offers->accept($stale, $b));

        $this->assertSame($a->id, $order->fresh()->delivery_agent_id);
    }

    public function test_accept_refused_for_other_city_offline_or_unapproved_rider(): void
    {
        $order = $this->makeOpenOffer();
        $riders = [
            $this->makeRider(['city_id' => $this->makeCity('itahari-offers')->id]),
            $this->makeRider(['availability' => 'offline']),
            $this->makeRider(['verification_status' => 'under_review']),
        ];

        foreach ($riders as $rider) {
            $this->assertFalse($this->offers->accept($order->fresh(), $rider));
        }

        $this->assertNull($order->fresh()->delivery_agent_id);
    }

    public function test_accept_refused_for_cancelled_order(): void
    {
        $order = $this->makeOpenOffer();
        $stale = Order::find($order->id);
        $order->transitionOrderStatus(OrderStatus::Cancelled);

        $this->assertFalse($this->offers->accept($stale, $this->makeRider()));
        $this->assertNull($order->fresh()->delivery_agent_id);
    }

    public function test_rider_with_two_open_deliveries_cannot_accept_more(): void
    {
        $rider = $this->makeRider();
        $this->assertTrue($this->offers->accept($this->makeOpenOffer(), $rider));
        $this->assertTrue($this->offers->accept($this->makeOpenOffer(), $rider));

        $this->assertFalse($this->offers->accept($this->makeOpenOffer(), $rider));
    }

    public function test_declined_offer_hides_until_pay_rises(): void
    {
        $rider = $this->makeRider();
        $order = $this->makeOpenOffer();

        $this->offers->decline($order, $rider);
        $this->assertCount(0, $this->offers->openOffersFor($rider));

        $this->travel(1)->minutes();
        $this->assertCount(0, $this->offers->openOffersFor($rider));

        $this->travel(1)->minutes();
        $this->assertSame([$order->id], $this->offers->openOffersFor($rider)->pluck('id')->all());
    }

    public function test_manual_assignment_records_rider_pay(): void
    {
        $rider = $this->makeRider();
        $order = $this->makeOpenOffer();

        $this->offers->assignManually($order, $rider, null);

        $order->refresh();
        $this->assertSame($rider->id, $order->delivery_agent_id);
        $this->assertSame('50.00', $order->rider_pay);
        $this->assertTrue(AuditLog::where('action', 'delivery_manually_assigned')->exists());
    }

    public function test_rider_cannot_go_offline_with_an_assigned_order(): void
    {
        $rider = $this->makeRider();
        $order = $this->makeOpenOffer();
        $this->offers->accept($order, $rider);

        $this->assertFalse($rider->goOffline());
        $this->assertSame('available', $rider->fresh()->availability);

        $order->update(['delivery_status' => DeliveryStatus::Delivered]);
        $this->assertTrue($rider->goOffline());
        $this->assertSame('offline', $rider->fresh()->availability);
        $this->assertTrue($rider->goOnline());
    }

    public function test_released_delivery_returns_to_the_board(): void
    {
        $rider = $this->makeRider();
        $other = $this->makeRider();
        $order = $this->makeOpenOffer();
        $this->offers->accept($order, $rider);

        $rider->releaseUnstartedDeliveries();

        $this->assertNull($order->fresh()->rider_pay);
        $this->assertSame([$order->id], $this->offers->openOffersFor($other)->pluck('id')->all());
    }
}
