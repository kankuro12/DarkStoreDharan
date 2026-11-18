<?php

namespace Tests\Feature\Rider;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Pages\WarehouseOperations;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Delivery\DeliveryOfferService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WarehouseOfferDispatchTest extends TestCase
{
    use DeliveryOfferFixtures, RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOfferFixtures();
        $this->warehouse->cities()->attach($this->city->id);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->manager = User::create([
            'name' => 'Dharan Manager',
            'email' => 'manager@example.com',
            'password' => 'secret-pass-123',
            'role' => UserRole::WarehouseManager,
            'warehouse_id' => $this->warehouse->id,
        ]);
    }

    public function test_dispatch_keeps_rider_who_accepted_offer(): void
    {
        $this->makeRider(); // created first, so the old auto-pick would grab this one
        $a = $this->makeRider();
        $order = $this->makeOpenOffer(['order_status' => OrderStatus::ReadyForDispatch]);
        app(DeliveryOfferService::class)->accept($order, $a);

        Livewire::actingAs($this->manager)->test(WarehouseOperations::class)->call('dispatchOrder', $order->id);

        $order->refresh();
        $this->assertSame($a->id, $order->delivery_agent_id);
        $this->assertSame(OrderStatus::Dispatched, $order->order_status);
    }

    public function test_manual_dispatch_records_rider_pay_and_audit(): void
    {
        $rider = $this->makeRider();
        $order = $this->makeOpenOffer(['order_status' => OrderStatus::ReadyForDispatch]);

        Livewire::actingAs($this->manager)->test(WarehouseOperations::class)->call('dispatchOrder', $order->id, $rider->id);

        $order->refresh();
        $this->assertSame($rider->id, $order->delivery_agent_id);
        $this->assertSame('50.00', $order->rider_pay);
        $this->assertTrue(AuditLog::where('action', 'delivery_manually_assigned')->exists());
    }

    public function test_manual_dispatch_rejects_rider_from_another_city(): void
    {
        $rider = $this->makeRider(['city_id' => $this->makeCity('itahari-offers')->id]);
        $order = $this->makeOpenOffer(['order_status' => OrderStatus::ReadyForDispatch]);

        Livewire::actingAs($this->manager)->test(WarehouseOperations::class)
            ->call('dispatchOrder', $order->id, $rider->id)
            ->assertStatus(422);

        $order->refresh();
        $this->assertNull($order->delivery_agent_id);
        $this->assertSame(OrderStatus::ReadyForDispatch, $order->order_status);
    }

    public function test_board_shows_stuck_badge_after_max_pay(): void
    {
        $this->freezeTime();
        $this->makeOpenOffer(['order_status' => OrderStatus::ReadyForDispatch]);

        $page = Livewire::actingAs($this->manager)->test(WarehouseOperations::class)
            ->assertSee('Offer Rs 50');

        $this->travel(12)->minutes();
        $page->call('$refresh')->assertSee('No rider at max pay');
    }
}
