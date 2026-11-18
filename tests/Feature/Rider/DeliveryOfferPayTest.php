<?php

namespace Tests\Feature\Rider;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryOfferPayTest extends TestCase
{
    use DeliveryOfferFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOfferFixtures();
    }

    public function test_starting_packing_opens_offer_for_unassigned_order(): void
    {
        $order = $this->makeOfferOrder(['order_status' => OrderStatus::Confirmed]);

        $order->transitionOrderStatus(OrderStatus::Processing);

        $this->assertNotNull($order->fresh()->offer_opened_at);
    }

    public function test_starting_packing_does_not_open_offer_when_rider_already_assigned(): void
    {
        $rider = $this->makeRider();
        $order = $this->makeOfferOrder(['order_status' => OrderStatus::Confirmed, 'delivery_agent_id' => $rider->id]);

        $order->transitionOrderStatus(OrderStatus::Processing);

        $this->assertNull($order->fresh()->offer_opened_at);
    }

    public function test_pay_starts_at_base_and_boosts_each_interval_up_to_max(): void
    {
        $this->freezeTime();
        $order = $this->openOffer();

        foreach ([0 => 50.0, 1 => 50.0, 2 => 60.0, 9 => 90.0, 10 => 100.0, 30 => 100.0] as $minutes => $pay) {
            $this->travelTo($order->offer_opened_at->copy()->addMinutes($minutes));
            $this->assertSame($pay, $order->fresh()->currentOfferPay(), "after {$minutes} min");
        }
    }

    public function test_pay_never_drops_below_base_when_max_is_lower(): void
    {
        $this->city->update(['rider_base_pay' => 80, 'rider_pay_max' => 60]);

        $this->assertSame(80.0, $this->openOffer()->currentOfferPay());
    }

    public function test_offer_is_stuck_one_interval_after_reaching_max(): void
    {
        $this->freezeTime();
        $order = $this->openOffer();

        $this->travel(11)->minutes();
        $this->assertFalse($order->fresh()->offerStuck());

        $this->travel(1)->minutes();
        $this->assertTrue($order->fresh()->offerStuck());

        $order->update(['delivery_agent_id' => $this->makeRider()->id]);
        $this->assertFalse($order->fresh()->offerStuck());
    }

    public function test_unopened_offer_has_no_pay(): void
    {
        $this->assertNull($this->makeOfferOrder()->currentOfferPay());
    }

    private function openOffer(): Order
    {
        $order = $this->makeOfferOrder(['order_status' => OrderStatus::Confirmed]);
        $order->transitionOrderStatus(OrderStatus::Processing);

        return $order->fresh();
    }
}
