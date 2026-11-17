<?php

namespace Tests\Feature\Vendor;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Filament\Resources\VendorPayouts\Pages\ManageVendorPayouts;
use App\Filament\Resources\VendorPayouts\VendorPayoutResource;
use App\Filament\Vendor\Pages\VendorSalesReport;
use App\Filament\Vendor\Widgets\VendorStockOverview;
use App\Models\Address;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\Vendor;
use App\Models\VendorPayout;
use App\Models\Warehouse;
use App\Services\Vendor\VendorSettlementService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesOnboardingFixtures;
use Tests\TestCase;

class VendorSettlementTest extends TestCase
{
    use CreatesOnboardingFixtures, RefreshDatabase;

    private City $city;

    private Warehouse $warehouse;

    private Vendor $vendor;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->city = $this->makeCity();
        $this->warehouse = $this->makeWarehouse($this->city);
        $this->vendor = $this->makeVendor(['city_id' => $this->city->id, 'verification_status' => VerificationStatus::Approved]);
        $this->variant = $this->makeSellableVariant($this->city, $this->warehouse, $this->vendor, 'Ilam Tea', 200);
    }

    /** A vendor-supplied order line on an order in the given state. */
    private function sale(int $quantity, float $unitSupplyPrice, OrderStatus $status = OrderStatus::Delivered, ?string $deliveredAt = '2026-11-10 12:00:00', ?Vendor $vendor = null, ?ProductVariant $variant = null): Order
    {
        $variant ??= $this->variant;
        $address = Address::create([
            'city_id' => $this->city->id,
            'full_name' => 'Customer Secret Name',
            'phone' => '9841999888',
            'area' => 'Hidden Tole',
            'street' => 'Private Street 7',
        ]);
        $order = $this->makeOrderFor(null, $this->warehouse, DeliveryStatus::Delivered, $this->city->id);
        $order->update(['order_status' => $status, 'delivered_at' => $deliveredAt, 'address_id' => $address->id]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $variant->product_id,
            'variant_id' => $variant->id,
            'vendor_id' => ($vendor ?? $this->vendor)->id,
            'unit_supply_price' => $unitSupplyPrice,
            'product_name' => $variant->product->name,
            'sku' => $variant->sku,
            'quantity' => $quantity,
            'unit_price' => 450,
            'total' => 450 * $quantity,
        ]);

        return $order;
    }

    public function test_statement_counts_only_delivered_orders_in_range_using_snapshot_price(): void
    {
        $this->sale(2, 200);                                               // counted: 400
        $this->sale(1, 220);                                               // counted at its own snapshot: 220
        $this->sale(5, 200, OrderStatus::Cancelled);                       // excluded
        $this->sale(5, 200, OrderStatus::Returned);                        // excluded
        $this->sale(3, 200, OrderStatus::Delivered, '2026-10-01 10:00:00'); // out of range
        $this->variant->update(['supply_price' => 999]);                   // later price change must not matter

        $statement = app(VendorSettlementService::class)->statement($this->vendor, now()->setDate(2026, 11, 1), now()->setDate(2026, 11, 30));

        $this->assertEquals(620, $statement['payable']);
        $this->assertEquals(0, $statement['paid']);
        $this->assertEquals(620, $statement['balance']);
        $this->assertCount(2, $statement['lines']);
        $this->assertEqualsCanonicalizing([400.0, 220.0], array_column($statement['lines'], 'amount'));
    }

    public function test_vendor_report_contains_no_customer_data(): void
    {
        $this->sale(2, 200);
        Filament::setCurrentPanel(Filament::getPanel('vendor'));
        $this->actingAs($this->vendor, 'vendor');

        Livewire::test(VendorSalesReport::class)
            ->set('data.from', '2026-11-01')
            ->set('data.to', '2026-11-30')
            ->assertSee($this->variant->sku)
            ->assertSee('400')
            ->assertDontSee('Customer Secret Name')
            ->assertDontSee('9841999888')
            ->assertDontSee('Private Street 7')
            ->assertDontSee('Hidden Tole');
    }

    public function test_vendor_sees_only_own_stock_and_sales(): void
    {
        $other = $this->makeVendor(['city_id' => $this->city->id, 'verification_status' => VerificationStatus::Approved]);
        $otherVariant = $this->makeSellableVariant($this->city, $this->warehouse, $other, 'Rival Tea', 150);
        $this->sale(4, 150, vendor: $other, variant: $otherVariant);
        Filament::setCurrentPanel(Filament::getPanel('vendor'));
        $this->actingAs($this->vendor, 'vendor');

        Livewire::test(VendorStockOverview::class)
            ->assertCanSeeTableRecords([$this->variant])
            ->assertCanNotSeeTableRecords([$otherVariant]);

        Livewire::test(VendorSalesReport::class)
            ->set('data.from', '2026-11-01')
            ->set('data.to', '2026-11-30')
            ->assertDontSee($otherVariant->sku);
    }

    public function test_finance_records_payout_and_balance_drops_catalog_manager_cannot(): void
    {
        $this->sale(2, 200);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $finance = $this->makeStaff(UserRole::Finance);
        $this->actingAs($finance);

        Livewire::test(ManageVendorPayouts::class)
            ->callAction('create', data: [
                'vendor_id' => $this->vendor->id,
                'period_from' => '2026-11-01',
                'period_to' => '2026-11-30',
                'amount' => 300,
                'method' => 'bank',
                'reference' => 'NABIL-TXN-1',
                'paid_at' => '2026-11-16',
            ])
            ->assertHasNoActionErrors();

        $payout = VendorPayout::first();
        $this->assertSame($finance->id, $payout->recorded_by);
        $statement = app(VendorSettlementService::class)->statement($this->vendor, now()->setDate(2026, 11, 1), now()->setDate(2026, 11, 30));
        $this->assertEquals(100, $statement['balance']);

        $this->flushSession();
        $this->actingAs($this->makeStaff(UserRole::CatalogManager))->get(VendorPayoutResource::getUrl('index'))->assertForbidden();
    }

    public function test_approved_vendor_dashboard_and_sales_page_render(): void
    {
        $this->actingAs($this->vendor, 'vendor');

        $this->get('/vendor')->assertOk()->assertSeeLivewire(VendorStockOverview::class); // dashboard widgets load lazily
        $this->get(VendorSalesReport::getUrl(panel: 'vendor'))->assertOk()->assertSee('Balance due');
    }

    public function test_payout_above_balance_is_refused(): void
    {
        $this->sale(2, 200);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->makeStaff(UserRole::Finance));

        Livewire::test(ManageVendorPayouts::class)
            ->callAction('create', data: [
                'vendor_id' => $this->vendor->id,
                'period_from' => '2026-11-01',
                'period_to' => '2026-11-30',
                'amount' => 500,
                'method' => 'bank',
                'reference' => 'NABIL-TXN-2',
                'paid_at' => '2026-11-16',
            ])
            ->assertHasActionErrors(['amount']);

        $this->assertSame(0, VendorPayout::count());
    }
}
