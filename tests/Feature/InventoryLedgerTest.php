<?php

namespace Tests\Feature;

use App\Enums\DeliveryStatus;
use App\Enums\InventoryMovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\City;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\StockReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class InventoryLedgerTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;

    private Warehouse $otherWarehouse;

    private ProductVariant $variant;

    private City $city;

    protected function setUp(): void
    {
        parent::setUp();

        $this->warehouse = Warehouse::create([
            'name' => 'Dharan Dark Store',
            'code' => 'WH-TEST-01',
            'address' => 'Putali Line, Dharan',
            'latitude' => 26.81,
            'longitude' => 87.28,
            'status' => 'active',
        ]);

        $this->otherWarehouse = Warehouse::create([
            'name' => 'Itahari Hub',
            'code' => 'WH-TEST-02',
            'address' => 'Main Highway, Itahari',
            'latitude' => 26.66,
            'longitude' => 87.28,
            'status' => 'active',
        ]);

        $this->city = City::create([
            'name' => 'Dharan',
            'slug' => 'dharan-test',
            'province' => 'Koshi Province',
            'status' => 'active',
            'cod_enabled' => true,
            'prepaid_enabled' => true,
            'default_delivery_fee' => 40,
            'free_delivery_minimum' => 1000,
            'estimated_delivery_minutes' => 30,
        ]);

        $product = Product::create([
            'name' => 'Test Cola',
            'slug' => 'test-cola',
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'TEST-COLA-500',
            'name' => '500ml',
            'price' => 85,
            'cod_allowed' => true,
            'status' => 'active',
        ]);
    }

    public function test_first_adjustment_writes_opening_balance_then_in_movement(): void
    {
        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 10,
            'reserved_quantity' => 0,
            'reorder_level' => 5,
        ]);

        app(InventoryService::class)->adjustStock(
            $this->warehouse->id,
            $this->variant->id,
            5,
            'Supplier delivery',
        );

        $movements = InventoryMovement::orderBy('id')->get();

        $this->assertCount(2, $movements);
        $this->assertSame(InventoryMovementType::Opening, $movements[0]->type);
        $this->assertSame(10, $movements[0]->quantity);
        $this->assertSame(10, $movements[0]->balance_after);
        $this->assertSame(InventoryMovementType::In, $movements[1]->type);
        $this->assertSame(5, $movements[1]->quantity);
        $this->assertSame(15, $movements[1]->balance_after);

        $this->assertSame(15, (int) Inventory::first()->quantity);
    }

    public function test_sale_confirmation_writes_out_movement_with_order_reference(): void
    {
        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 10,
            'reserved_quantity' => 3,
            'reorder_level' => 5,
        ]);

        $order = $this->makeOrder();

        StockReservation::create([
            'order_id' => $order->id,
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 3,
            'status' => 'reserved',
            'expires_at' => now()->addMinutes(20),
        ]);

        app(StockReservationService::class)->confirm($order);

        $inventory = Inventory::first();
        $this->assertSame(7, (int) $inventory->quantity);
        $this->assertSame(0, (int) $inventory->reserved_quantity);

        $sale = InventoryMovement::where('type', InventoryMovementType::Out)->first();
        $this->assertNotNull($sale);
        $this->assertSame(-3, $sale->quantity);
        $this->assertSame(7, $sale->balance_after);
        $this->assertSame($order->order_number, $sale->reference_code);
        $this->assertSame($order->id, $sale->reference_id);
    }

    public function test_cancelling_after_confirmation_restores_stock_with_in_movement(): void
    {
        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 10,
            'reserved_quantity' => 3,
            'reorder_level' => 5,
        ]);

        $order = $this->makeOrder();

        StockReservation::create([
            'order_id' => $order->id,
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 3,
            'status' => 'reserved',
            'expires_at' => now()->addMinutes(20),
        ]);

        $reservations = app(StockReservationService::class);
        $reservations->confirm($order);
        $reservations->release($order);

        $this->assertSame(10, (int) Inventory::first()->quantity);

        $restore = InventoryMovement::where('type', InventoryMovementType::In)
            ->where('quantity', 3)
            ->first();

        $this->assertNotNull($restore);
        $this->assertSame(10, $restore->balance_after);
    }

    public function test_transfer_writes_paired_entries_and_moves_stock(): void
    {
        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 10,
            'reserved_quantity' => 0,
            'reorder_level' => 5,
        ]);

        Inventory::create([
            'warehouse_id' => $this->otherWarehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 0,
            'reserved_quantity' => 0,
            'reorder_level' => 5,
        ]);

        app(InventoryService::class)->transfer(
            $this->warehouse->id,
            $this->otherWarehouse->id,
            $this->variant->id,
            4,
            'Rebalance stock',
        );

        $source = Inventory::where('warehouse_id', $this->warehouse->id)->first();
        $destination = Inventory::where('warehouse_id', $this->otherWarehouse->id)->first();

        $this->assertSame(6, (int) $source->quantity);
        $this->assertSame(4, (int) $destination->quantity);

        $out = InventoryMovement::where('type', InventoryMovementType::TransferOut)->first();
        $in = InventoryMovement::where('type', InventoryMovementType::TransferIn)->first();

        $this->assertSame(-4, $out->quantity);
        $this->assertSame(4, $in->quantity);
        $this->assertSame($out->reference_code, $in->reference_code);
        $this->assertStringStartsWith('TRF-', $out->reference_code);
    }

    public function test_ledger_entries_cannot_be_updated_or_deleted(): void
    {
        app(InventoryService::class)->adjustStock(
            $this->warehouse->id,
            $this->variant->id,
            5,
            'Opening count',
        );

        $movement = InventoryMovement::first();

        try {
            $movement->update(['quantity' => 999]);
            $this->fail('Updating a ledger entry should have thrown.');
        } catch (RuntimeException) {
            // expected
        }

        try {
            $movement->delete();
            $this->fail('Deleting a ledger entry should have thrown.');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(5, (int) InventoryMovement::where('type', InventoryMovementType::In)->first()->quantity);
    }

    private function makeOrder(): Order
    {
        return Order::create([
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
            'delivery_status' => DeliveryStatus::Assigned,
            'placed_at' => now(),
        ]);
    }
}
