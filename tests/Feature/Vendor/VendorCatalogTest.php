<?php

namespace Tests\Feature\Vendor;

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Vendor\Resources\VendorProducts\Pages\CreateVendorProduct;
use App\Filament\Vendor\Resources\VendorProducts\Pages\EditVendorProduct;
use App\Filament\Vendor\Resources\VendorProducts\Pages\ListVendorProducts;
use App\Filament\Vendor\Resources\VendorProducts\VendorProductResource;
use App\Models\Category;
use App\Models\City;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\Checkout\CheckoutService;
use App\Services\Sms\SmsService;
use App\Services\Verification\VerificationService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesOnboardingFixtures;
use Tests\TestCase;

class VendorCatalogTest extends TestCase
{
    use CreatesOnboardingFixtures, RefreshDatabase;

    private City $city;

    private Warehouse $warehouse;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(SmsService::class)->shouldReceive('send')->andReturnTrue();
        $this->city = $this->makeCity('Dharan');
        $this->warehouse = $this->makeWarehouse($this->city);
        $this->vendor = $this->makeVendor([
            'city_id' => $this->city->id,
            'legal_name' => 'Secret Supplier Pvt. Ltd.',
            'trade_name' => 'HiddenBrandTrader',
            'verification_status' => VerificationStatus::Approved,
        ]);
    }

    private function asVendor(?Vendor $vendor = null): void
    {
        Filament::setCurrentPanel(Filament::getPanel('vendor'));
        $this->actingAs($vendor ?? $this->vendor, 'vendor');
    }

    public function test_vendor_submission_is_pending_and_hidden_from_storefront_until_approved(): void
    {
        $this->asVendor();
        $category = Category::create(['name' => 'Pantry', 'slug' => 'pantry']);

        Livewire::test(CreateVendorProduct::class)
            ->fillForm([
                'name' => 'Ilam Tea 250g',
                'category_id' => $category->id,
                'description' => 'Orthodox tea',
                'variants' => [['name' => '250g', 'sku' => 'ILAM-TEA-250', 'supply_price' => 180, 'weight_kg' => 0.25]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('name', 'Ilam Tea 250g')->first();
        $this->assertSame('pending_review', $product->status);
        $this->assertSame($this->vendor->id, $product->vendor_id);
        $this->assertEquals(180, $product->variants->first()->supply_price);

        $this->get('/?city_id='.$this->city->id); // storefront needs a chosen city
        $this->get('/products/'.$product->slug)->assertNotFound();
    }

    public function test_vendor_sees_and_edits_only_own_products(): void
    {
        $other = $this->makeVendor(['city_id' => $this->city->id, 'verification_status' => VerificationStatus::Approved]);
        $mine = $this->makeSellableVariant($this->city, $this->warehouse, $this->vendor, 'Mine')->product;
        $theirs = $this->makeSellableVariant($this->city, $this->warehouse, $other, 'Theirs')->product;
        $house = $this->makeSellableVariant($this->city, $this->warehouse, null, 'House')->product;
        $mine->update(['status' => 'pending_review']);
        $this->asVendor();

        Livewire::test(ListVendorProducts::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs, $house]);

        $this->get(VendorProductResource::getUrl('edit', ['record' => $theirs]))->assertNotFound();
    }

    public function test_vendor_cannot_edit_live_product_or_change_status_and_prices(): void
    {
        $variant = $this->makeSellableVariant($this->city, $this->warehouse, $this->vendor);
        $this->asVendor();

        $this->get(VendorProductResource::getUrl('edit', ['record' => $variant->product]))->assertForbidden();

        $category = Category::create(['name' => 'Pantry', 'slug' => 'pantry']);
        $variant->product->update(['status' => 'draft', 'category_id' => $category->id]);
        Livewire::test(EditVendorProduct::class, ['record' => $variant->product->getKey()])
            ->fillForm(['name' => 'Renamed', 'status' => 'active'])
            ->call('save')
            ->assertHasNoFormErrors();

        $product = $variant->product->fresh();
        $this->assertSame('Renamed', $product->name);
        $this->assertSame('pending_review', $product->status);
        $this->assertEquals(450, ProductPrice::where('product_variant_id', $variant->id)->value('price'));
    }

    public function test_unapproved_vendor_cannot_open_products_resource(): void
    {
        $pending = $this->makeVendor(['city_id' => $this->city->id, 'verification_status' => VerificationStatus::Submitted]);
        $this->asVendor($pending);

        $this->get(VendorProductResource::getUrl('index'))->assertRedirect();
    }

    public function test_admin_approves_submission_only_with_city_price(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->makeStaff(UserRole::CatalogManager));
        $product = Product::create(['name' => 'Pending Item', 'slug' => 'pending-item', 'status' => 'pending_review', 'vendor_id' => $this->vendor->id]);
        $variant = $product->variants()->create(['sku' => 'PEND-1', 'name' => '1pc', 'price' => 100, 'supply_price' => 60, 'status' => 'active']);

        Livewire::test(ListProducts::class)
            ->callTableAction('approveSubmission', $product)
            ->assertNotified();
        $this->assertSame('pending_review', $product->fresh()->status);

        ProductPrice::create(['product_variant_id' => $variant->id, 'city_id' => $this->city->id, 'price' => 120]);
        Livewire::test(ListProducts::class)->callTableAction('approveSubmission', $product);
        $this->assertSame('active', $product->fresh()->status);
    }

    public function test_admin_rejects_submission_back_to_draft(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->makeStaff(UserRole::CatalogManager));
        $product = Product::create(['name' => 'Bad Item', 'slug' => 'bad-item', 'status' => 'pending_review', 'vendor_id' => $this->vendor->id]);

        Livewire::test(ListProducts::class)->callTableAction('rejectSubmission', $product, data: ['reason' => 'Missing label photo']);

        $this->assertSame('draft', $product->fresh()->status);
    }

    public function test_checkout_snapshots_vendor_and_supply_price(): void
    {
        $variant = $this->makeSellableVariant($this->city, $this->warehouse, $this->vendor, supplyPrice: 300);

        $order = app(CheckoutService::class)->placeOrder([
            'city_id' => $this->city->id,
            'items' => [['variant_id' => $variant->id, 'quantity' => 2]],
            'payment_method' => 'cod',
            'address' => ['full_name' => 'Buyer', 'phone' => '9800000009', 'area' => 'Bhanuchowk', 'street' => 'Main'],
        ]);

        $item = OrderItem::where('order_id', $order->id)->first();
        $this->assertSame($this->vendor->id, $item->vendor_id);
        $this->assertEquals(300, $item->unit_supply_price);
    }

    public function test_customers_never_see_vendor(): void
    {
        $variant = $this->makeSellableVariant($this->city, $this->warehouse, $this->vendor, 'Vendor Honey');
        $product = $variant->product;

        $order = app(CheckoutService::class)->placeOrder([
            'city_id' => $this->city->id,
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            'payment_method' => 'cod',
            'address' => ['full_name' => 'Buyer', 'phone' => '9800000009', 'area' => 'Bhanuchowk', 'street' => 'Main'],
        ]);

        $responses = [
            $this->get('/?city_id='.$this->city->id),
            $this->get('/products/'.$product->slug),
            $this->getJson('/api/products?city_id='.$this->city->id),
            $this->getJson('/api/products/'.$product->slug.'?city_id='.$this->city->id),
            $this->get('/orders/'.$order->order_number.'/tracking'),
            $this->getJson('/api/orders/'.$order->order_number),
        ];

        foreach ($responses as $response) {
            $response->assertOk();
            $body = $response->getContent();
            $this->assertStringNotContainsString('Secret Supplier', $body);
            $this->assertStringNotContainsString('HiddenBrandTrader', $body);
            $this->assertStringNotContainsString('vendor_id', $body);
            $this->assertStringNotContainsString('supply_price', $body);
        }

        $this->assertArrayNotHasKey('vendor_id', $product->toArray());
        $this->assertArrayNotHasKey('supply_price', $variant->toArray());
    }

    public function test_suspending_vendor_hides_their_products_and_reactivation_restores_only_those(): void
    {
        $live = $this->makeSellableVariant($this->city, $this->warehouse, $this->vendor, 'Live')->product;
        $alreadyOff = $this->makeSellableVariant($this->city, $this->warehouse, $this->vendor, 'Off')->product;
        $alreadyOff->update(['status' => 'inactive']);
        $admin = $this->makeStaff(UserRole::CatalogManager);
        $service = app(VerificationService::class);

        $service->suspend($this->vendor, $admin, 'Quality issue');
        $this->assertSame('inactive', $live->fresh()->status);

        $service->reactivate($this->vendor->fresh(), $admin);
        $this->assertSame('active', $live->fresh()->status);
        $this->assertSame('inactive', $alreadyOff->fresh()->status);
    }
}
