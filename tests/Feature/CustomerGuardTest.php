<?php

namespace Tests\Feature;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\City;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Checkout\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class CustomerGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_registration_creates_a_customer_in_the_customers_table(): void
    {
        $response = $this->post('/register', [
            'name' => 'Aayush Shrestha',
            'email' => 'aayush@example.com',
            'password' => 'secret-pass-123',
            'password_confirmation' => 'secret-pass-123',
        ]);

        $response->assertRedirect('/');

        $this->assertDatabaseHas('customers', ['email' => 'aayush@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'aayush@example.com']);

        $this->assertTrue(Auth::guard('customer')->check());
        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_customer_can_access_storefront_account_pages(): void
    {
        $customer = Customer::create([
            'name' => 'Aayush',
            'email' => 'aayush@example.com',
            'password' => 'secret-pass-123',
        ]);

        $this->actingAs($customer, 'customer')
            ->get('/account/orders')
            ->assertOk();
    }

    public function test_staff_user_cannot_access_storefront_account_pages(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'secret-pass-123',
            'role' => UserRole::SuperAdmin,
        ]);

        $this->actingAs($admin)
            ->get('/account/orders')
            ->assertRedirect('/login');
    }

    public function test_customer_logout_ends_only_the_customer_session(): void
    {
        $customer = Customer::create([
            'name' => 'Aayush',
            'email' => 'aayush@example.com',
            'password' => 'secret-pass-123',
        ]);

        $this->actingAs($customer, 'customer')
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertFalse(Auth::guard('customer')->check());
    }

    public function test_orders_placed_by_a_customer_reference_customer_id(): void
    {
        $customer = Customer::create([
            'name' => 'Aayush',
            'email' => 'aayush@example.com',
            'password' => 'secret-pass-123',
        ]);

        [, $city, $variant] = $this->makeCatalog();

        $order = app(CheckoutService::class)->placeOrder([
            'city_id' => $city->id,
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            'payment_method' => 'cod',
        ], $customer);

        $this->assertSame($customer->id, $order->customer_id);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'customer_id' => $customer->id]);
    }

    public function test_storefront_return_request_is_recorded_against_the_customer(): void
    {
        $customer = Customer::create([
            'name' => 'Aayush',
            'email' => 'aayush@example.com',
            'password' => 'secret-pass-123',
        ]);

        [$warehouse, $city] = $this->makeCatalog();

        $order = Order::create([
            'order_number' => 'ORD-RETURN-1',
            'customer_id' => $customer->id,
            'city_id' => $city->id,
            'warehouse_id' => $warehouse->id,
            'subtotal' => 100,
            'grand_total' => 140,
            'payment_method' => PaymentMethod::Cod,
            'order_status' => OrderStatus::Delivered,
            'payment_status' => PaymentStatus::Paid,
            'delivery_status' => DeliveryStatus::Delivered,
            'placed_at' => now(),
            'delivered_at' => now(),
        ]);

        $this->actingAs($customer, 'customer')
            ->post("/orders/{$order->order_number}/return", ['reason_code' => 'damaged'])
            ->assertRedirect();

        $this->assertDatabaseHas('return_requests', [
            'order_id' => $order->id,
            'customer_id' => $customer->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'return_requested',
            'user_id' => null,
        ]);
    }

    /**
     * @return array{0: Warehouse, 1: City, 2: ProductVariant}
     */
    private function makeCatalog(): array
    {
        $warehouse = Warehouse::create([
            'name' => 'Test WH',
            'code' => 'WH-GUARD-01',
            'address' => 'Test address',
            'latitude' => 26.81,
            'longitude' => 87.28,
            'status' => 'active',
        ]);

        $city = City::create([
            'name' => 'Dharan',
            'slug' => 'dharan-guard-test',
            'province' => 'Koshi Province',
            'status' => 'active',
            'cod_enabled' => true,
            'prepaid_enabled' => true,
            'default_delivery_fee' => 40,
            'free_delivery_minimum' => 1000,
            'estimated_delivery_minutes' => 30,
        ]);

        $city->warehouses()->attach($warehouse->id, [
            'priority' => 1,
            'delivery_minutes' => 30,
            'delivery_fee' => 40,
            'status' => 'active',
        ]);

        $product = Product::create([
            'name' => 'Guard Cola',
            'slug' => 'guard-cola',
            'status' => 'active',
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'GUARD-COLA-500',
            'name' => '500ml',
            'price' => 85,
            'cod_allowed' => true,
            'status' => 'active',
        ]);

        Inventory::create([
            'warehouse_id' => $warehouse->id,
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'reserved_quantity' => 0,
            'reorder_level' => 5,
        ]);

        return [$warehouse, $city, $variant];
    }
}
