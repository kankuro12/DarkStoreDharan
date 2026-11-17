<?php

namespace Tests\Concerns;

use App\Enums\BusinessType;
use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\City;
use App\Models\DeliveryAgent;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use Illuminate\Support\Str;

/** Shared builders for rider / vendor onboarding tests. */
trait CreatesOnboardingFixtures
{
    protected function makeCity(string $name = 'Dharan'): City
    {
        return City::create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(4),
            'province' => 'Koshi Province',
            'status' => 'active',
            'cod_enabled' => true,
            'prepaid_enabled' => true,
            'default_delivery_fee' => 40,
            'free_delivery_minimum' => 1000,
            'estimated_delivery_minutes' => 30,
        ]);
    }

    protected function makeWarehouse(?City $city = null, string $code = 'WH-ONB-01'): Warehouse
    {
        $warehouse = Warehouse::create([
            'name' => 'Dark Store '.$code,
            'code' => $code,
            'address' => 'Putali Line',
            'latitude' => 26.81,
            'longitude' => 87.28,
            'status' => 'active',
        ]);

        if ($city) {
            $warehouse->cities()->attach($city->id);
        }

        return $warehouse;
    }

    protected function makeRider(array $attributes = []): DeliveryAgent
    {
        return DeliveryAgent::create(array_merge([
            'name' => 'Ram Bahadur',
            'phone' => '98'.random_int(10000000, 99999999),
            'email' => Str::random(8).'@rider.test',
            'password' => 'rider-secret',
            'city_id' => $attributes['city_id'] ?? $this->makeCity()->id,
            'status' => 'active',
            'availability' => 'available',
            'verification_status' => 'draft',
        ], $attributes));
    }

    protected function makeVendor(array $attributes = []): Vendor
    {
        return Vendor::create(array_merge([
            'legal_name' => 'Himalayan Traders Pvt. Ltd.',
            'trade_name' => 'Himalayan Traders',
            'business_type' => BusinessType::PrivateLimited,
            'pan_number' => (string) random_int(100000000, 999999999),
            'phone' => '98'.random_int(10000000, 99999999),
            'email' => Str::random(8).'@vendor.test',
            'password' => 'vendor-secret',
            'city_id' => $attributes['city_id'] ?? $this->makeCity()->id,
            'status' => 'active',
            'verification_status' => 'draft',
        ], $attributes));
    }

    /** A product sellable in $city from $warehouse (city price + 10 in stock), optionally supplied by $vendor. */
    protected function makeSellableVariant(City $city, Warehouse $warehouse, ?Vendor $vendor = null, string $name = 'Organic Honey 500g', float $supplyPrice = 300): ProductVariant
    {
        $product = Product::create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(4),
            'status' => 'active',
            'vendor_id' => $vendor?->id,
        ]);
        $variant = $product->variants()->create([
            'sku' => 'SKU-'.Str::upper(Str::random(6)),
            'name' => '500g',
            'price' => 450,
            'supply_price' => $supplyPrice,
            'status' => 'active',
        ]);
        ProductPrice::create(['product_variant_id' => $variant->id, 'city_id' => $city->id, 'price' => 450]);
        Inventory::create(['warehouse_id' => $warehouse->id, 'product_variant_id' => $variant->id, 'quantity' => 10, 'reserved_quantity' => 0]);

        return $variant;
    }

    protected function makeOrderFor(?DeliveryAgent $rider, Warehouse $warehouse, DeliveryStatus $deliveryStatus, ?int $cityId = null): Order
    {
        return Order::create([
            'order_number' => 'ORD-'.strtoupper(Str::random(8)),
            'city_id' => $cityId ?? $rider?->city_id,
            'warehouse_id' => $warehouse->id,
            'delivery_agent_id' => $rider?->id,
            'subtotal' => 255,
            'discount' => 0,
            'delivery_fee' => 40,
            'tax' => 0,
            'grand_total' => 295,
            'payment_method' => PaymentMethod::Cod,
            'order_status' => OrderStatus::ReadyForDispatch,
            'payment_status' => PaymentStatus::Unpaid,
            'delivery_status' => $deliveryStatus,
            'placed_at' => now(),
        ]);
    }

    protected function makeStaff(UserRole $role, array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => $role->value.' user',
            'email' => Str::random(8).'@staff.test',
            'password' => 'password',
            'role' => $role,
        ], $attributes));
    }
}
