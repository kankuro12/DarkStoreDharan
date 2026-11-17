<?php

namespace Tests\Concerns;

use App\Enums\UserRole;
use App\Models\City;
use App\Models\DeliveryAgent;
use App\Models\User;
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
