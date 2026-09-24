<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\OutOfStockException;
use App\Jobs\ReleaseExpiredReservations;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Cart\CartService;
use App\Services\Catalog\CityCatalogService;
use App\Services\Checkout\CheckoutService;
use App\Services\Inventory\StockReservationService;
use App\Services\Payment\PaymentMethodService;
use App\Services\Returns\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FastDeliveryArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected City $dharan;

    protected City $biratnagar;

    protected Warehouse $whDharan;

    protected Warehouse $whBiratnagar;

    protected Product $coke;

    protected ProductVariant $cokeVariant;

    protected ProductVariant $milkVariant;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create Dharan and Biratnagar
        $this->dharan = City::create([
            'name' => 'Dharan',
            'slug' => 'dharan',
            'status' => 'active',
            'cod_enabled' => true,
            'prepaid_enabled' => true,
            'default_delivery_fee' => 40.00,
            'free_delivery_minimum' => 500.00,
            'estimated_delivery_minutes' => 30,
        ]);

        $this->biratnagar = City::create([
            'name' => 'Biratnagar',
            'slug' => 'biratnagar',
            'status' => 'active',
            'cod_enabled' => true,
            'prepaid_enabled' => true,
            'default_delivery_fee' => 50.00,
            'free_delivery_minimum' => 1000.00,
            'estimated_delivery_minutes' => 45,
        ]);

        // 2. Create Warehouses
        $this->whDharan = Warehouse::create([
            'name' => 'Dharan Central Dark Store',
            'code' => 'WH-DHN',
            'status' => 'active',
        ]);

        $this->whBiratnagar = Warehouse::create([
            'name' => 'Biratnagar Hub',
            'code' => 'WH-BRT',
            'status' => 'active',
        ]);

        // Connect warehouse to city
        $this->dharan->warehouses()->attach($this->whDharan->id, ['priority' => 1, 'delivery_minutes' => 30, 'status' => 'active']);
        $this->biratnagar->warehouses()->attach($this->whBiratnagar->id, ['priority' => 1, 'delivery_minutes' => 45, 'status' => 'active']);

        // 3. Create Products and Variants
        $cat = Category::create(['name' => 'Snacks', 'slug' => 'snacks', 'status' => 'active']);
        $brand = Brand::create(['name' => 'Coca-Cola', 'slug' => 'coke', 'status' => 'active']);

        $this->coke = Product::create([
            'name' => 'Coca-Cola 500ml',
            'slug' => 'coke-500ml',
            'category_id' => $cat->id,
            'brand_id' => $brand->id,
            'status' => 'active',
        ]);

        $this->cokeVariant = ProductVariant::create([
            'product_id' => $this->coke->id,
            'sku' => 'COKE-500',
            'name' => '500ml',
            'price' => 85.00,
            'cod_allowed' => true,
            'status' => 'active',
        ]);

        $milk = Product::create([
            'name' => 'DDC Fresh Milk',
            'slug' => 'ddc-milk',
            'category_id' => $cat->id,
            'status' => 'active',
        ]);

        $this->milkVariant = ProductVariant::create([
            'product_id' => $milk->id,
            'sku' => 'DDC-MILK',
            'name' => '500ml',
            'price' => 45.00,
            'cod_allowed' => true,
            'status' => 'active',
        ]);

        // 4. Inventories:
        // Dharan has 10 Coke and 5 Milk
        Inventory::create([
            'warehouse_id' => $this->whDharan->id,
            'product_variant_id' => $this->cokeVariant->id,
            'quantity' => 10,
            'reserved_quantity' => 0,
        ]);
        Inventory::create([
            'warehouse_id' => $this->whDharan->id,
            'product_variant_id' => $this->milkVariant->id,
            'quantity' => 5,
            'reserved_quantity' => 0,
        ]);

        // Biratnagar has 10 Coke, but 0 Milk (Out of stock in Biratnagar)
        Inventory::create([
            'warehouse_id' => $this->whBiratnagar->id,
            'product_variant_id' => $this->cokeVariant->id,
            'quantity' => 10,
            'reserved_quantity' => 0,
        ]);
        Inventory::create([
            'warehouse_id' => $this->whBiratnagar->id,
            'product_variant_id' => $this->milkVariant->id,
            'quantity' => 0,
            'reserved_quantity' => 0,
        ]);
    }

    /**
     * Test 1: City Catalog isolation (§3, §4).
     */
    public function test_catalog_shows_correct_availability_per_city(): void
    {
        $service = app(CityCatalogService::class);

        // In Dharan, both Coke and Milk must be in stock
        $dharanCatalog = $service->getProductsForCity($this->dharan->id);
        $dharanProducts = collect($dharanCatalog['products']);

        $cokeInDharan = $dharanProducts->firstWhere('id', $this->coke->id);
        $this->assertNotNull($cokeInDharan);
        $this->assertTrue($cokeInDharan['is_available']);

        // In Biratnagar, Milk has 0 available stock
        $biratnagarCatalog = $service->getProductsForCity($this->biratnagar->id);
        $biratnagarProducts = collect($biratnagarCatalog['products']);

        $milkInBiratnagar = $biratnagarProducts->firstWhere('slug', 'ddc-milk');
        $this->assertFalse($milkInBiratnagar['is_available']);
    }

    /**
     * Test 2: City-specific price overrides (§8).
     */
    public function test_city_specific_pricing_override_is_resolved(): void
    {
        // Add city price for Dharan: sale price Rs 75 instead of Rs 85
        ProductPrice::create([
            'product_variant_id' => $this->cokeVariant->id,
            'city_id' => $this->dharan->id,
            'price' => 85.00,
            'sale_price' => 75.00,
        ]);

        $dharanPrice = $this->cokeVariant->getPriceForCity($this->dharan->id);
        $this->assertEquals(75.00, $dharanPrice['price']);
        $this->assertTrue($dharanPrice['is_sale']);

        // Biratnagar has no override, should fallback to standard 85.00
        $biratnagarPrice = $this->cokeVariant->getPriceForCity($this->biratnagar->id);
        $this->assertEquals(85.00, $biratnagarPrice['price']);
        $this->assertFalse($biratnagarPrice['is_sale']);
    }

    /**
     * Test 3: Transactional stock reservation and concurrency protection (§21, §38.2).
     */
    public function test_stock_reservation_holds_stock_and_prevents_overselling(): void
    {
        $reservationService = app(StockReservationService::class);

        // In Dharan, only 5 units of Milk exist
        $items = [
            ['variant_id' => $this->milkVariant->id, 'quantity' => 3],
        ];

        // 1. Reserve 3 units
        $reservations = $reservationService->reserve($this->whDharan->id, $items, null, 'session_1');
        $this->assertCount(1, $reservations);

        // Check inventory state: quantity = 5, reserved = 3, available = 2
        $inv = Inventory::where('warehouse_id', $this->whDharan->id)
            ->where('product_variant_id', $this->milkVariant->id)
            ->first();

        $this->assertEquals(5, $inv->quantity);
        $this->assertEquals(3, $inv->reserved_quantity);
        $this->assertEquals(2, $inv->available);

        // 2. Try reserving 3 more units (only 2 left) -> Must throw OutOfStockException
        $this->expectException(OutOfStockException::class);
        $reservationService->reserve($this->whDharan->id, $items, null, 'session_2');
    }

    /**
     * Test 4: Cart revalidation on city switch without silent item removal (§14, §38.5).
     */
    public function test_cart_revalidation_notifies_when_switching_to_city_without_stock(): void
    {
        $cartService = app(CartService::class);

        // Customer in Dharan adds Milk (which is available in Dharan)
        $cartService->addItem($this->milkVariant->id, 2, $this->dharan->id);
        $this->assertEquals(2, $cartService->getItemCount());

        // Customer switches city to Biratnagar (where Milk is 0 in stock)
        $result = $cartService->changeCity($this->biratnagar->id);

        $this->assertTrue($result['has_changes']);
        $this->assertCount(1, $result['unavailable_items']);
        $this->assertEquals('DDC Fresh Milk (500ml)', $result['unavailable_items'][0]['name']);
        $this->assertEquals(0, $result['unavailable_items'][0]['available_in_new_city']);
    }

    /**
     * Test 5: Section 30 Checkout Validation API endpoint (§30).
     */
    public function test_checkout_validation_endpoint_conforms_to_section_30_specification(): void
    {
        $response = $this->postJson('/api/checkout/validate', [
            'city_id' => $this->dharan->id,
            'items' => [
                ['variant_id' => $this->cokeVariant->id, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'valid',
            'items' => [
                '*' => ['variant_id', 'sku', 'name', 'quantity', 'available', 'price', 'total'],
            ],
            'subtotal',
            'discount',
            'delivery_fee',
            'total',
            'estimated_delivery',
            'payment_methods' => [
                'prepaid',
                'cod',
                'cod_disabled_reasons',
                'gateways',
            ],
        ]);

        $response->assertJson([
            'valid' => true,
            'subtotal' => 170.00,
            'delivery_fee' => 40.00,
            'total' => 210.00,
        ]);
    }

    /**
     * Test 6: Multi-level COD resolution engine (§10, §11, §38.4).
     */
    public function test_cod_resolution_engine_blocks_cod_when_order_exceeds_limit(): void
    {
        $paymentService = app(PaymentMethodService::class);

        // 1. Order below Rs 15,000 allows COD
        $items = [['variant_id' => $this->cokeVariant->id, 'quantity' => 2]];
        $result = $paymentService->resolveAvailableMethods($this->dharan->id, 500.00, $items);
        $this->assertTrue($result['cod']);

        // 2. Order above Rs 15,000 blocks COD
        $largeTotal = 16000.00;
        $largeResult = $paymentService->resolveAvailableMethods($this->dharan->id, $largeTotal, $items);
        $this->assertFalse($largeResult['cod']);
        $this->assertNotEmpty($largeResult['cod_disabled_reasons']);

        // 3. User with cod_blocked = true gets blocked
        $blockedUser = User::create([
            'name' => 'Bad Customer',
            'email' => 'blocked@test.com',
            'password' => 'secret',
            'cod_blocked' => true,
        ]);

        $userResult = $paymentService->resolveAvailableMethods($this->dharan->id, 200.00, $items, $blockedUser);
        $this->assertFalse($userResult['cod']);
    }

    /**
     * Test 7: Place order via CheckoutService and verify immutable snapshot (§16, §38.2).
     */
    public function test_order_placement_creates_immutable_snapshot_and_deducts_stock_for_cod(): void
    {
        $checkoutService = app(CheckoutService::class);

        $orderPayload = [
            'city_id' => $this->dharan->id,
            'items' => [
                ['variant_id' => $this->cokeVariant->id, 'quantity' => 2],
            ],
            'payment_method' => 'cod',
            'address' => [
                'full_name' => 'Bishal Rai',
                'phone' => '9800000001',
                'area' => 'Bhanuchowk',
                'street' => 'College Road',
            ],
        ];

        $order = $checkoutService->placeOrder($orderPayload);

        $this->assertEquals(OrderStatus::Confirmed, $order->order_status);
        $this->assertEquals(PaymentStatus::Unpaid, $order->payment_status);
        $this->assertEquals(170.00, $order->subtotal);
        $this->assertEquals(40.00, $order->delivery_fee);
        $this->assertEquals(210.00, $order->grand_total);

        // Check immutable order items snapshot (§16)
        $this->assertCount(1, $order->items);
        $item = $order->items->first();
        $this->assertEquals('Coca-Cola 500ml - 500ml', $item->product_name);
        $this->assertEquals(85.00, $item->unit_price);
        $this->assertEquals(2, $item->quantity);

        // Inventory should be permanently deducted for COD order
        $inv = Inventory::where('warehouse_id', $this->whDharan->id)
            ->where('product_variant_id', $this->cokeVariant->id)
            ->first();

        $this->assertEquals(8, $inv->quantity); // 10 - 2 = 8
        $this->assertEquals(0, $inv->reserved_quantity);
    }

    /**
     * Test 8: Prepaid payment server-to-server gateway callback verification (§18, §38.2).
     */
    public function test_server_side_prepaid_payment_callback_confirms_order(): void
    {
        $checkoutService = app(CheckoutService::class);

        // Place prepaid order (e.g. eSewa)
        $order = $checkoutService->placeOrder([
            'city_id' => $this->dharan->id,
            'items' => [
                ['variant_id' => $this->cokeVariant->id, 'quantity' => 1],
            ],
            'payment_method' => 'esewa',
            'address' => [
                'full_name' => 'Online Payer',
                'phone' => '9800000002',
                'area' => 'BPKIHS',
                'street' => 'Hospital Lane',
            ],
        ]);

        $this->assertEquals(OrderStatus::Pending, $order->order_status);
        $this->assertEquals(PaymentStatus::Pending, $order->payment_status);

        // Server-side gateway callback comes in
        $response = $this->postJson('/api/payments/callback', [
            'order_number' => $order->order_number,
            'transaction_id' => 'ESEWA-TXN-998877',
            'status' => 'SUCCESS',
            'amount' => $order->grand_total,
            'gateway' => 'esewa',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'order_status' => OrderStatus::Confirmed->value,
            'payment_status' => PaymentStatus::Paid->value,
        ]);

        $order->refresh();
        $this->assertEquals(OrderStatus::Confirmed, $order->order_status);
        $this->assertEquals(PaymentStatus::Paid, $order->payment_status);
    }

    /**
     * Test 9: Auto-expiring stock reservation cleanup job (§19, §38.6).
     */
    public function test_expired_stock_reservations_are_automatically_released(): void
    {
        $reservationService = app(StockReservationService::class);

        // Create an expired reservation directly
        $inv = Inventory::where('warehouse_id', $this->whDharan->id)
            ->where('product_variant_id', $this->cokeVariant->id)
            ->first();

        $inv->increment('reserved_quantity', 3);

        StockReservation::create([
            'product_variant_id' => $this->cokeVariant->id,
            'warehouse_id' => $this->whDharan->id,
            'quantity' => 3,
            'status' => 'reserved',
            'expires_at' => now()->subMinutes(5), // Expired!
        ]);

        $this->assertEquals(7, $inv->fresh()->available); // 10 - 3 = 7

        // Run the cleanup job
        (new ReleaseExpiredReservations)->handle($reservationService);

        // Reserved hold should be cleared back to available
        $this->assertEquals(10, $inv->fresh()->available);
    }

    /**
     * Test 10: Storefront Home renders successfully with plain Blade + Tailwind (§39).
     */
    public function test_storefront_home_page_returns_successful_response(): void
    {
        $response = $this->get('/?city_id='.$this->dharan->id);
        $response->assertStatus(200);
        $response->assertSee('Dharan');
        $response->assertSee('Coca-Cola 500ml');
        $response->assertSee('30 min');
    }

    /**
     * Test 11: Product details page renders with Schema.org JSON-LD structured data (§43).
     */
    public function test_product_detail_page_renders_with_schema_org_json_ld(): void
    {
        $response = $this->get('/products/'.$this->coke->slug.'?city_id='.$this->dharan->id);
        $response->assertStatus(200);
        $response->assertSee('Coca-Cola 500ml');
        $response->assertSee('schema.org');
        $response->assertSee('InStock');
        $response->assertSee('500ml');
    }

    /**
     * Test 12: Live search autocomplete endpoint returns instant matches (§42.2).
     */
    public function test_live_search_endpoint_returns_instant_matches(): void
    {
        $response = $this->getJson('/api/products?city_id='.$this->dharan->id.'&q=Coke');
        $response->assertStatus(200);
        $response->assertJsonPath('data.products.0.name', 'Coca-Cola 500ml');
        $response->assertJsonPath('data.products.0.is_available', true);
    }

    /**
     * Test 13: Returns & Refunds complete lifecycle workflow (§47).
     */
    public function test_returns_and_refunds_complete_workflow(): void
    {
        $checkoutService = app(CheckoutService::class);
        $returnService = app(ReturnService::class);

        // 1. Customer places and receives order
        $order = $checkoutService->placeOrder([
            'city_id' => $this->dharan->id,
            'items' => [
                ['variant_id' => $this->cokeVariant->id, 'quantity' => 1],
            ],
            'payment_method' => 'cod',
            'address' => [
                'full_name' => 'Returner',
                'phone' => '9800000099',
                'area' => 'Bhanuchowk',
                'street' => 'Line 1',
            ],
        ]);

        $order->transitionOrderStatus(OrderStatus::Delivered, 'Delivered to customer');

        // 2. Customer requests return
        $returnReq = $returnService->requestReturn($order, 'damaged', 'Bottle cap was damaged and leaked');
        $this->assertEquals('requested', $returnReq->status);

        // 3. Support approves return
        $returnService->reviewReturn($returnReq, true, 'Approved for return pickup');
        $this->assertEquals('approved', $returnReq->fresh()->status);

        // 4. Warehouse inspects item and writes off (damaged)
        $returnService->inspectAndRestock($returnReq, false, 'Leaked, unsellable condition');
        $this->assertEquals('inspected', $returnReq->fresh()->status);
        $this->assertFalse($returnReq->fresh()->restocked);
        $this->assertEquals(OrderStatus::Returned, $order->fresh()->order_status);

        // 5. Finance completes refund
        $returnService->processRefund($returnReq, 'Reconciled cash refund with rider');
        $this->assertEquals('completed', $returnReq->fresh()->status);
        $this->assertEquals(PaymentStatus::Refunded, $order->fresh()->payment_status);
    }

    /**
     * Test 14: System Health check endpoint (§44, §46).
     */
    public function test_system_health_check_endpoint(): void
    {
        $response = $this->getJson('/api/health');
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'ok',
            'checks' => [
                'database' => true,
                'cache' => true,
            ],
        ]);
    }

    /**
     * Test 15: Checkout page renders with cart items.
     */
    public function test_checkout_page_renders_with_cart_items(): void
    {
        $cartService = app(CartService::class);
        $cartService->addItem($this->cokeVariant->id, 2, $this->dharan->id);

        $response = $this->withSession(['cart.city_id' => $this->dharan->id])->get('/checkout');
        $response->assertStatus(200);
        $response->assertSee('Checkout');
        $response->assertSee('Dharan');
        $response->assertSee('Coca-Cola 500ml');
    }

    /**
     * Test 16: Order tracking page renders with delivery SLA stepper.
     */
    public function test_order_tracking_page_renders_with_stepper(): void
    {
        $checkoutService = app(CheckoutService::class);
        $order = $checkoutService->placeOrder([
            'city_id' => $this->dharan->id,
            'items' => [
                ['variant_id' => $this->cokeVariant->id, 'quantity' => 1],
            ],
            'payment_method' => 'cod',
            'address' => [
                'full_name' => 'Tracking Tester',
                'phone' => '9811111111',
                'area' => 'Bhanuchowk',
                'street' => 'Line 2',
            ],
        ]);

        $response = $this->get("/orders/{$order->order_number}/tracking");
        $response->assertStatus(200);
        $response->assertSee($order->order_number);
        $response->assertSee('Delivery Timeline');
        $response->assertSee('SLA Tracking');
        $response->assertSee('Order Confirmed');
    }

    /**
     * Test 17: Compliance Privacy and Terms pages render successfully (§48).
     */
    public function test_compliance_privacy_and_terms_pages_render(): void
    {
        $resPrivacy = $this->get('/privacy-policy');
        $resPrivacy->assertStatus(200);
        $resPrivacy->assertSee('Privacy');
        $resPrivacy->assertSee('Customer Data Protection Policy');

        $resTerms = $this->get('/terms-of-service');
        $resTerms->assertStatus(200);
        $resTerms->assertSee('Terms of Service');
    }

    /**
     * Test 18: No default city chosen on first visit; customer must choose city (§3, §38.5).
     */
    public function test_first_time_customer_sees_city_selection_screen_with_no_default_city(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertViewHas('currentCity', null);
        $response->assertSee('Where should we deliver today?');
        $response->assertSee('Select Your Delivery City');
        $response->assertSee('Shop in Dharan');
        $response->assertSee('Shop in Biratnagar');
        $response->assertDontSee('Daily Essentials at your Door in');
    }

    /**
     * Test 19: Customer selecting a city locks catalog and renders localized products.
     */
    public function test_customer_selecting_city_locks_catalog_to_that_city(): void
    {
        $response = $this->get('/?city_id='.$this->dharan->id);
        $response->assertStatus(200);
        $response->assertViewHas('currentCity');
        $this->assertEquals($this->dharan->id, session('cart.city_id'));
        $response->assertSee('Daily Essentials at your Door in');
        $response->assertSee('Fast Delivery in Dharan');
        $response->assertSee('Coca-Cola 500ml');
    }

    /**
     * Test 20: Product detail page redirects to city picker when no city is selected.
     */
    public function test_product_page_requires_prior_city_selection(): void
    {
        $response = $this->get('/products/'.$this->coke->slug);
        $response->assertRedirect('/');
        $response->assertSessionHas('info');
    }

    /**
     * Test 21: Cities added from admin are immediately available for customer selection.
     */
    public function test_admin_added_city_is_immediately_available_on_storefront(): void
    {
        $pokhara = City::create([
            'name' => 'Pokhara',
            'slug' => 'pokhara',
            'province' => 'Gandaki',
            'status' => 'active',
            'cod_enabled' => true,
            'prepaid_enabled' => true,
            'default_delivery_fee' => 45.00,
            'free_delivery_minimum' => 600.00,
            'estimated_delivery_minutes' => 35,
        ]);

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Pokhara');
        $response->assertSee('Shop in Pokhara');
        $response->assertSee('~35m SLA');
    }
}
