<?php

namespace Tests\Feature\Rider;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Address;
use App\Models\City;
use App\Models\Customer;
use App\Models\DeliveryAgent;
use App\Models\Order;
use App\Models\Warehouse;

trait DeliveryOfferFixtures
{
    protected City $city;

    protected Warehouse $warehouse;

    protected function setUpOfferFixtures(): void
    {
        $this->city = $this->makeCity('dharan-offers');

        $this->warehouse = Warehouse::create([
            'name' => 'Dharan Dark Store',
            'code' => 'WH-OFFER-01',
            'address' => 'Putali Line, Dharan',
            'latitude' => 26.81,
            'longitude' => 87.28,
            'status' => 'active',
        ]);
    }

    protected function makeCity(string $slug): City
    {
        return City::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'province' => 'Koshi Province',
            'status' => 'active',
            'cod_enabled' => true,
            'prepaid_enabled' => true,
            'default_delivery_fee' => 40,
            'free_delivery_minimum' => 1000,
            'estimated_delivery_minutes' => 30,
        ]);
    }

    protected function makeRider(array $attributes = []): DeliveryAgent
    {
        static $n = 0;
        $n++;

        return DeliveryAgent::create(array_merge([
            'name' => "Rider {$n}",
            'phone' => '98123456'.str_pad((string) $n, 2, '0', STR_PAD_LEFT),
            'email' => "rider{$n}@example.com",
            'password' => 'rider-secret',
            'city_id' => $this->city->id,
            'status' => 'active',
            'availability' => 'available',
            'verification_status' => 'approved',
            'training_completed_at' => now(),
        ], $attributes));
    }

    protected function makeAddress(array $attributes = []): Address
    {
        $customer = Customer::create([
            'name' => 'Sita Sharma',
            'email' => 'sita'.uniqid().'@example.com',
            'password' => 'secret-pass',
        ]);

        return Address::create(array_merge([
            'customer_id' => $customer->id,
            'city_id' => $this->city->id,
            'full_name' => 'Sita Sharma',
            'phone' => '9800000001',
            'area' => 'Putali Line',
            'street' => 'Gali 4 House 22',
        ], $attributes));
    }

    protected function makeOfferOrder(array $attributes = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-'.strtoupper(uniqid()),
            'city_id' => $this->city->id,
            'warehouse_id' => $this->warehouse->id,
            'subtotal' => 255,
            'discount' => 0,
            'delivery_fee' => 40,
            'tax' => 0,
            'grand_total' => 295,
            'payment_method' => PaymentMethod::Cod,
            'order_status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Unpaid,
            'placed_at' => now(),
        ], $attributes));
    }

    /** An order already on the board (offer opened now). */
    protected function makeOpenOffer(array $attributes = []): Order
    {
        return $this->makeOfferOrder(array_merge(['offer_opened_at' => now()], $attributes));
    }
}
