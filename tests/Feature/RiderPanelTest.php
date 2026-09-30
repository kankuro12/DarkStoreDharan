<?php

namespace Tests\Feature;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Rider\Pages\MyDeliveries;
use App\Models\City;
use App\Models\DeliveryAgent;
use App\Models\Order;
use App\Models\Warehouse;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RiderPanelTest extends TestCase
{
    use RefreshDatabase;

    private City $city;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->city = City::create([
            'name' => 'Dharan',
            'slug' => 'dharan-rider-test',
            'province' => 'Koshi Province',
            'status' => 'active',
            'cod_enabled' => true,
            'prepaid_enabled' => true,
            'default_delivery_fee' => 40,
            'free_delivery_minimum' => 1000,
            'estimated_delivery_minutes' => 30,
        ]);

        $this->warehouse = Warehouse::create([
            'name' => 'Dharan Dark Store',
            'code' => 'WH-RIDER-01',
            'address' => 'Putali Line, Dharan',
            'latitude' => 26.81,
            'longitude' => 87.28,
            'status' => 'active',
        ]);
    }

    public function test_rider_can_authenticate_on_the_delivery_guard(): void
    {
        $agent = $this->makeAgent();

        $this->assertTrue(auth('delivery')->attempt([
            'email' => $agent->email,
            'password' => 'rider-secret',
        ]));
    }

    public function test_guest_is_redirected_to_rider_login(): void
    {
        $this->get('/rider/my-deliveries')->assertRedirect('/rider/login');
    }

    public function test_active_rider_can_open_my_deliveries(): void
    {
        $agent = $this->makeAgent();

        $this->actingAs($agent, 'delivery')
            ->get('/rider/my-deliveries')
            ->assertOk()
            ->assertSee('My Deliveries');
    }

    public function test_inactive_rider_is_denied_panel_access(): void
    {
        $agent = $this->makeAgent(['status' => 'inactive']);

        $this->actingAs($agent, 'delivery')
            ->get('/rider/my-deliveries')
            ->assertForbidden();
    }

    public function test_rider_marks_assigned_order_delivered(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('rider'));

        $agent = $this->makeAgent();
        $order = $this->makeOrder($agent, DeliveryStatus::OutForDelivery);

        Livewire::actingAs($agent, 'delivery')
            ->test(MyDeliveries::class)
            ->call('markDelivered', $order->id)
            ->assertHasNoErrors();

        $order->refresh();

        $this->assertSame(DeliveryStatus::Delivered, $order->delivery_status);
        $this->assertSame(OrderStatus::Delivered, $order->order_status);
        $this->assertNotNull($order->delivered_at);
    }

    public function test_rider_cannot_act_on_another_riders_order(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('rider'));

        $agent = $this->makeAgent();
        $otherAgent = $this->makeAgent([
            'email' => 'other.rider@example.com',
            'phone' => '9812345678',
        ]);

        $order = $this->makeOrder($otherAgent, DeliveryStatus::OutForDelivery);

        Livewire::actingAs($agent, 'delivery')
            ->test(MyDeliveries::class)
            ->call('markDelivered', $order->id);

        $this->assertSame(DeliveryStatus::OutForDelivery, $order->fresh()->delivery_status);
    }

    private function makeAgent(array $attributes = []): DeliveryAgent
    {
        return DeliveryAgent::create(array_merge([
            'name' => 'Bikram Rai',
            'phone' => '9812345670',
            'email' => 'rider@example.com',
            'password' => 'rider-secret',
            'city_id' => $this->city->id,
            'status' => 'active',
            'availability' => 'available',
        ], $attributes));
    }

    private function makeOrder(DeliveryAgent $agent, DeliveryStatus $deliveryStatus): Order
    {
        return Order::create([
            'order_number' => 'ORD-'.strtoupper(uniqid()),
            'city_id' => $this->city->id,
            'warehouse_id' => $this->warehouse->id,
            'delivery_agent_id' => $agent->id,
            'subtotal' => 255,
            'discount' => 0,
            'delivery_fee' => 40,
            'tax' => 0,
            'grand_total' => 295,
            'payment_method' => PaymentMethod::Cod,
            'order_status' => OrderStatus::Dispatched,
            'payment_status' => PaymentStatus::Unpaid,
            'delivery_status' => $deliveryStatus,
            'placed_at' => now(),
            'dispatched_at' => now(),
        ]);
    }
}
