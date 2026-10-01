<?php

namespace Tests\Feature;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Inventories\Pages\ManageInventories;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\City;
use App\Models\Order;
use App\Models\User;
use App\Models\Warehouse;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WarehouseManagerScopeTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $home;

    private Warehouse $other;

    private City $city;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->home = Warehouse::create([
            'name' => 'Home Store',
            'code' => 'WH-HOME-01',
            'address' => 'Home Street',
            'latitude' => 26.81,
            'longitude' => 87.28,
            'status' => 'active',
        ]);

        $this->other = Warehouse::create([
            'name' => 'Other Store',
            'code' => 'WH-OTHER-01',
            'address' => 'Other Street',
            'latitude' => 26.66,
            'longitude' => 87.28,
            'status' => 'active',
        ]);

        $this->city = City::create([
            'name' => 'Dharan',
            'slug' => 'dharan-scope-test',
            'province' => 'Koshi Province',
            'status' => 'active',
            'cod_enabled' => true,
            'prepaid_enabled' => true,
            'default_delivery_fee' => 40,
            'free_delivery_minimum' => 1000,
            'estimated_delivery_minutes' => 30,
        ]);
    }

    public function test_manager_sees_only_orders_from_their_warehouse(): void
    {
        $homeOrder = $this->makeOrder($this->home, 'ORD-HOME-1');
        $otherOrder = $this->makeOrder($this->other, 'ORD-OTHER-1');

        Livewire::actingAs($this->makeManager())
            ->test(ListOrders::class)
            ->assertSee($homeOrder->order_number)
            ->assertDontSee($otherOrder->order_number);
    }

    public function test_manager_cannot_open_another_warehouses_order_directly(): void
    {
        $otherOrder = $this->makeOrder($this->other, 'ORD-OTHER-2');

        // Scoped route binding hides the record entirely (404), never leaks it.
        $this->actingAs($this->makeManager())
            ->get("/admin/orders/{$otherOrder->id}")
            ->assertNotFound();
    }

    public function test_manager_inventory_page_is_pinned_to_their_warehouse(): void
    {
        Livewire::actingAs($this->makeManager())
            ->test(ManageInventories::class)
            ->assertSet('warehouseId', $this->home->id)
            ->set('warehouseId', $this->other->id)
            ->assertSet('warehouseId', $this->home->id);
    }

    public function test_manager_is_blocked_from_global_configuration_pages(): void
    {
        $manager = $this->makeManager();

        $this->actingAs($manager)->get('/admin/cities')->assertForbidden();
        $this->actingAs($manager)->get('/admin/products')->assertForbidden();
        $this->actingAs($manager)->get('/admin/products/create')->assertForbidden();
        $this->actingAs($manager)->get('/admin/settings')->assertForbidden();
        $this->actingAs($manager)->get('/admin/delivery-agents')->assertForbidden();
    }

    public function test_manager_keeps_access_to_their_operational_pages(): void
    {
        $manager = $this->makeManager();

        $this->actingAs($manager)->get('/admin/inventories')->assertOk();
        $this->actingAs($manager)->get('/admin/inventory-movements')->assertOk();
        $this->actingAs($manager)->get('/admin/warehouse-operations')->assertOk();
        $this->actingAs($manager)->get('/admin/warehouses')->assertOk();
        $this->actingAs($manager)->get('/admin/return-requests')->assertOk();
    }

    public function test_super_admin_still_sees_every_warehouse(): void
    {
        $homeOrder = $this->makeOrder($this->home, 'ORD-HOME-9');
        $otherOrder = $this->makeOrder($this->other, 'ORD-OTHER-9');

        Livewire::actingAs($this->makeAdmin())
            ->test(ListOrders::class)
            ->assertSee($homeOrder->order_number)
            ->assertSee($otherOrder->order_number);
    }

    public function test_other_staff_roles_stay_unrestricted(): void
    {
        $picker = User::create([
            'name' => 'Picker',
            'email' => 'picker@example.com',
            'password' => 'secret-pass-123',
            'role' => UserRole::PickerPacker,
            'warehouse_id' => $this->home->id,
        ]);

        $homeOrder = $this->makeOrder($this->home, 'ORD-HOME-7');
        $otherOrder = $this->makeOrder($this->other, 'ORD-OTHER-7');

        Livewire::actingAs($picker)
            ->test(ListOrders::class)
            ->assertSee($homeOrder->order_number)
            ->assertSee($otherOrder->order_number);
    }

    private function makeManager(): User
    {
        return User::create([
            'name' => 'Dharan Manager',
            'email' => 'manager@example.com',
            'password' => 'secret-pass-123',
            'role' => UserRole::WarehouseManager,
            'warehouse_id' => $this->home->id,
        ]);
    }

    private function makeAdmin(): User
    {
        return User::create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => 'secret-pass-123',
            'role' => UserRole::SuperAdmin,
        ]);
    }

    private function makeOrder(Warehouse $warehouse, string $number): Order
    {
        return Order::create([
            'order_number' => $number,
            'city_id' => $this->city->id,
            'warehouse_id' => $warehouse->id,
            'subtotal' => 100,
            'grand_total' => 140,
            'payment_method' => PaymentMethod::Cod,
            'order_status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Unpaid,
            'delivery_status' => DeliveryStatus::Pending,
            'placed_at' => now(),
        ]);
    }
}
