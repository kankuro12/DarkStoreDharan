<?php

namespace Tests\Feature\Storefront;

use App\Enums\UserRole;
use App\Filament\Resources\DeliveryZones\Pages\ManageDeliveryZones;
use App\Models\City;
use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\ProductVariant;
use App\Services\Checkout\CheckoutService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\CreatesOnboardingFixtures;
use Tests\TestCase;

class DeliveryAreaTest extends TestCase
{
    use CreatesOnboardingFixtures, RefreshDatabase;

    private City $city;

    private ProductVariant $variant;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->city = $this->makeCity();
        $this->variant = $this->makeSellableVariant($this->city, $this->makeWarehouse($this->city));
        $this->customer = Customer::create(['name' => 'Aayush', 'email' => 'aayush@example.com', 'password' => 'secret-pass-123']);
    }

    private function zone(array $attributes = []): DeliveryZone
    {
        return DeliveryZone::create(array_merge(['city_id' => $this->city->id, 'name' => 'Bhanuchowk, Ward 3', 'delivery_fee' => 30], $attributes));
    }

    private function placeOrder(array $address)
    {
        return app(CheckoutService::class)->placeOrder([
            'city_id' => $this->city->id,
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 1]],
            'payment_method' => 'cod',
            'address' => array_merge(['full_name' => 'Aayush', 'phone' => '9800000001', 'street' => 'House 4'], $address),
        ], $this->customer);
    }

    public function test_checkout_uses_the_chosen_admin_area_and_ignores_typed_text(): void
    {
        $zone = $this->zone();

        $order = $this->placeOrder(['delivery_zone_id' => $zone->id, 'area' => 'Somewhere I typed']);

        $this->assertSame('Bhanuchowk, Ward 3', $order->address->area);
        $this->assertSame($zone->id, $order->address->delivery_zone_id);
    }

    public function test_checkout_refuses_missing_area_when_city_has_preset_areas(): void
    {
        $this->zone();

        $this->expectException(ValidationException::class);
        $this->placeOrder(['area' => 'Typed area']);
    }

    public function test_checkout_refuses_area_from_another_city_or_inactive(): void
    {
        $this->zone();
        $other = DeliveryZone::create(['city_id' => $this->makeCity('Itahari')->id, 'name' => 'Itahari Chowk']);
        $inactive = $this->zone(['name' => 'Closed area', 'status' => 'inactive']);

        foreach ([$other, $inactive] as $zone) {
            try {
                $this->placeOrder(['delivery_zone_id' => $zone->id]);
                $this->fail('Order placed with an invalid area');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('delivery_zone_id', $e->errors());
            }
        }
    }

    public function test_city_without_preset_areas_still_accepts_typed_area(): void
    {
        $order = $this->placeOrder(['area' => 'Putali Line']);

        $this->assertSame('Putali Line', $order->address->area);
    }

    public function test_saved_address_must_pick_preset_area(): void
    {
        $zone = $this->zone();
        $payload = ['city_id' => $this->city->id, 'full_name' => 'Aayush', 'phone' => '9800000001', 'street' => 'House 4'];

        $this->actingAs($this->customer, 'customer')
            ->post(route('storefront.account.addresses.store'), $payload + ['area' => 'Typed'])
            ->assertSessionHasErrors('delivery_zone_id');

        $this->actingAs($this->customer, 'customer')
            ->post(route('storefront.account.addresses.store'), $payload + ['delivery_zone_id' => $zone->id])
            ->assertSessionHasNoErrors();

        $this->assertSame('Bhanuchowk, Ward 3', $this->customer->addresses()->first()->area);
    }

    public function test_checkout_page_shows_area_dropdown_not_text_box(): void
    {
        $this->zone();
        $this->withSession(['cart.city_id' => $this->city->id, 'cart.items' => [$this->variant->id => ['variant_id' => $this->variant->id, 'quantity' => 1]]]);

        $this->actingAs($this->customer, 'customer')
            ->get(route('storefront.checkout.show'))
            ->assertOk()
            ->assertSee('Bhanuchowk, Ward 3')
            ->assertSee('name="delivery_zone_id" required', false)
            ->assertDontSee('type="text" name="area"', false);
    }

    public function test_admin_can_create_a_delivery_area(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($this->makeStaff(UserRole::SuperAdmin))
            ->test(ManageDeliveryZones::class)
            ->callAction('create', ['city_id' => $this->city->id, 'name' => 'Dharan-8, Bijayapur', 'delivery_fee' => 40, 'estimated_minutes' => 25])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('delivery_zones', ['name' => 'Dharan-8, Bijayapur', 'city_id' => $this->city->id]);
    }
}
