<?php

namespace Database\Seeders;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Address;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\Coupon;
use App\Models\DeliveryAgent;
use App\Models\DeliveryZone;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Warehouses
        $whDharan = Warehouse::create([
            'name' => 'Dharan Central Dark Store',
            'code' => 'WH-DHN-01',
            'address' => 'Putali Line, Ward 3, Dharan',
            'latitude' => 26.8124000,
            'longitude' => 87.2834000,
            'status' => 'active',
        ]);

        $whItahari = Warehouse::create([
            'name' => 'Itahari Express Hub',
            'code' => 'WH-ITH-01',
            'address' => 'Main Highway, Ward 4, Itahari',
            'latitude' => 26.6667000,
            'longitude' => 87.2833000,
            'status' => 'active',
        ]);

        $whBiratnagar = Warehouse::create([
            'name' => 'Biratnagar Regional Warehouse',
            'code' => 'WH-BRT-01',
            'address' => 'Roadcess Chowk, Biratnagar',
            'latitude' => 26.4525000,
            'longitude' => 87.2718000,
            'status' => 'active',
        ]);

        // 2. Create Users
        $admin = User::create([
            'name' => 'Super Administrator',
            'email' => 'admin@darkstore.np',
            'password' => Hash::make('password'),
            'role' => UserRole::SuperAdmin,
            'phone' => '9800000000',
        ]);

        $dharanManager = User::create([
            'name' => 'Dharan DarkStore Manager',
            'email' => 'dharan.manager@darkstore.np',
            'password' => Hash::make('password'),
            'role' => UserRole::WarehouseManager,
            'phone' => '9801111111',
            'warehouse_id' => $whDharan->id,
        ]);

        $picker = User::create([
            'name' => 'Suman Picker',
            'email' => 'picker@darkstore.np',
            'password' => Hash::make('password'),
            'role' => UserRole::PickerPacker,
            'phone' => '9802222222',
            'warehouse_id' => $whDharan->id,
        ]);

        $customer = User::create([
            'name' => 'Aayush Shrestha',
            'email' => 'customer@darkstore.np',
            'password' => Hash::make('password'),
            'role' => UserRole::Customer,
            'phone' => '9842000000',
        ]);

        // 3. Create Cities
        $cityDharan = City::create([
            'name' => 'Dharan',
            'slug' => 'dharan',
            'province' => 'Koshi Province',
            'status' => 'active',
            'cod_enabled' => true,
            'prepaid_enabled' => true,
            'default_delivery_fee' => 40.00,
            'free_delivery_minimum' => 1000.00,
            'estimated_delivery_minutes' => 30,
        ]);

        $cityItahari = City::create([
            'name' => 'Itahari',
            'slug' => 'itahari',
            'province' => 'Koshi Province',
            'status' => 'active',
            'cod_enabled' => true,
            'prepaid_enabled' => true,
            'default_delivery_fee' => 45.00,
            'free_delivery_minimum' => 1200.00,
            'estimated_delivery_minutes' => 35,
        ]);

        $cityBiratnagar = City::create([
            'name' => 'Biratnagar',
            'slug' => 'biratnagar',
            'province' => 'Koshi Province',
            'status' => 'active',
            'cod_enabled' => true,
            'prepaid_enabled' => true,
            'default_delivery_fee' => 50.00,
            'free_delivery_minimum' => 1500.00,
            'estimated_delivery_minutes' => 45,
        ]);

        $cityKathmandu = City::create([
            'name' => 'Kathmandu',
            'slug' => 'kathmandu',
            'province' => 'Bagmati Province',
            'status' => 'active',
            'cod_enabled' => true,
            'prepaid_enabled' => true,
            'default_delivery_fee' => 80.00,
            'free_delivery_minimum' => 2000.00,
            'estimated_delivery_minutes' => 60,
        ]);

        // 4. Warehouse-City Connections (§5.3 priority fulfillment)
        // Dharan is served primarily by Dharan Dark Store
        $cityDharan->warehouses()->attach($whDharan->id, [
            'priority' => 1,
            'delivery_minutes' => 30,
            'delivery_fee' => 40.00,
            'status' => 'active',
        ]);
        // Dharan can be backed up by Itahari hub if needed
        $cityDharan->warehouses()->attach($whItahari->id, [
            'priority' => 2,
            'delivery_minutes' => 55,
            'delivery_fee' => 60.00,
            'status' => 'active',
        ]);

        // Itahari served by Itahari hub, backed up by Dharan
        $cityItahari->warehouses()->attach($whItahari->id, [
            'priority' => 1,
            'delivery_minutes' => 35,
            'delivery_fee' => 45.00,
            'status' => 'active',
        ]);
        $cityItahari->warehouses()->attach($whDharan->id, [
            'priority' => 2,
            'delivery_minutes' => 50,
            'delivery_fee' => 60.00,
            'status' => 'active',
        ]);

        // Biratnagar served by Biratnagar warehouse, backed up by Itahari
        $cityBiratnagar->warehouses()->attach($whBiratnagar->id, [
            'priority' => 1,
            'delivery_minutes' => 45,
            'delivery_fee' => 50.00,
            'status' => 'active',
        ]);
        $cityBiratnagar->warehouses()->attach($whItahari->id, [
            'priority' => 2,
            'delivery_minutes' => 65,
            'delivery_fee' => 70.00,
            'status' => 'active',
        ]);

        // 5. Delivery Zones
        $zoneBhanuchowk = DeliveryZone::create([
            'city_id' => $cityDharan->id,
            'name' => 'Bhanuchowk Central',
            'postal_code' => '56700',
            'radius_km' => 3.0,
            'delivery_fee' => 30.00,
            'minimum_order' => 100.00,
            'estimated_minutes' => 20,
            'cod_enabled' => true,
            'status' => 'active',
        ]);

        $zonePindeshwor = DeliveryZone::create([
            'city_id' => $cityDharan->id,
            'name' => 'Pindeshwor / BPKIHS Area',
            'postal_code' => '56700',
            'radius_km' => 6.0,
            'delivery_fee' => 45.00,
            'minimum_order' => 150.00,
            'estimated_minutes' => 35,
            'cod_enabled' => true,
            'status' => 'active',
        ]);

        $zoneChatara = DeliveryZone::create([
            'city_id' => $cityDharan->id,
            'name' => 'Chatara Line / Ghopa',
            'postal_code' => '56700',
            'radius_km' => 8.0,
            'delivery_fee' => 50.00,
            'minimum_order' => 200.00,
            'estimated_minutes' => 40,
            'cod_enabled' => true,
            'status' => 'active',
        ]);

        // 6. Categories
        $catGroceries = Category::create([
            'name' => 'Groceries & Staples',
            'slug' => 'groceries-staples',
            'image' => 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=400&q=80',
            'sort_order' => 1,
            'status' => 'active',
        ]);

        $catDairy = Category::create([
            'name' => 'Dairy & Breakfast',
            'slug' => 'dairy-breakfast',
            'image' => 'https://images.unsplash.com/photo-1550583724-b2692b85b150?auto=format&fit=crop&w=400&q=80',
            'sort_order' => 2,
            'status' => 'active',
        ]);

        $catSnacks = Category::create([
            'name' => 'Snacks & Beverages',
            'slug' => 'snacks-beverages',
            'image' => 'https://images.unsplash.com/photo-1563729784474-d77dbb933a9e?auto=format&fit=crop&w=400&q=80',
            'sort_order' => 3,
            'status' => 'active',
        ]);

        $catPersonal = Category::create([
            'name' => 'Personal Care & Hygiene',
            'slug' => 'personal-care',
            'image' => 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?auto=format&fit=crop&w=400&q=80',
            'sort_order' => 4,
            'status' => 'active',
        ]);

        // 7. Brands
        $brandCoke = Brand::create(['name' => 'Coca-Cola', 'slug' => 'coca-cola']);
        $brandWaiWai = Brand::create(['name' => 'Wai Wai', 'slug' => 'wai-wai']);
        $brandDdc = Brand::create(['name' => 'DDC Nepal', 'slug' => 'ddc-nepal']);
        $brandAmul = Brand::create(['name' => 'Amul', 'slug' => 'amul']);
        $brandUnilever = Brand::create(['name' => 'Unilever', 'slug' => 'unilever']);
        $brandBritannia = Brand::create(['name' => 'Britannia', 'slug' => 'britannia']);

        // 8. Products & Variants & Inventories & City Prices
        // Product 1: Coca-Cola
        $coke = Product::create([
            'name' => 'Coca-Cola Refreshing Soft Drink',
            'slug' => 'coca-cola-refreshing-soft-drink',
            'brand_id' => $brandCoke->id,
            'category_id' => $catSnacks->id,
            'description' => 'Original Taste Coca-Cola sparkling soda drink. Chilled delivery in 30 minutes.',
            'image' => 'https://images.unsplash.com/photo-1622483767028-3f66f32aef97?auto=format&fit=crop&w=600&q=80',
            'status' => 'active',
        ]);

        $coke250 = ProductVariant::create([
            'product_id' => $coke->id,
            'sku' => 'COKE-CAN-250ML',
            'barcode' => '8901764012201',
            'name' => '250ml Can',
            'price' => 65.00,
            'weight_kg' => 0.28,
            'cod_allowed' => true,
        ]);

        $coke500 = ProductVariant::create([
            'product_id' => $coke->id,
            'sku' => 'COKE-PET-500ML',
            'barcode' => '8901764012218',
            'name' => '500ml Bottle',
            'price' => 85.00,
            'weight_kg' => 0.55,
            'cod_allowed' => true,
        ]);

        $coke15L = ProductVariant::create([
            'product_id' => $coke->id,
            'sku' => 'COKE-PET-1500ML',
            'barcode' => '8901764012225',
            'name' => '1.5L Family Pack',
            'price' => 170.00,
            'weight_kg' => 1.60,
            'cod_allowed' => true,
        ]);

        // Product 2: Wai Wai Noodles
        $waiwai = Product::create([
            'name' => 'Wai Wai Quick Instant Noodles Chicken',
            'slug' => 'wai-wai-quick-chicken-noodles',
            'brand_id' => $brandWaiWai->id,
            'category_id' => $catGroceries->id,
            'description' => 'Iconic ready-to-eat spiced chicken noodles. Authentic Nepali favorite snack.',
            'image' => 'https://images.unsplash.com/photo-1612927601601-6638404737ce?auto=format&fit=crop&w=600&q=80',
            'status' => 'active',
        ]);

        $waiwaiSingle = ProductVariant::create([
            'product_id' => $waiwai->id,
            'sku' => 'WAIWAI-CHK-75G',
            'barcode' => '8901234567890',
            'name' => '75g Single Pack',
            'price' => 25.00,
            'weight_kg' => 0.08,
            'cod_allowed' => true,
        ]);

        $waiwaiBox = ProductVariant::create([
            'product_id' => $waiwai->id,
            'sku' => 'WAIWAI-CHK-BOX12',
            'barcode' => '8901234567891',
            'name' => 'Pack of 12 (Special Save)',
            'price' => 280.00,
            'weight_kg' => 0.95,
            'cod_allowed' => true,
        ]);

        // Product 3: DDC Fresh Toned Milk
        $ddcMilk = Product::create([
            'name' => 'DDC Standard Pasteurized Fresh Milk',
            'slug' => 'ddc-standard-fresh-milk',
            'brand_id' => $brandDdc->id,
            'category_id' => $catDairy->id,
            'description' => 'Cold chain pasteurized fresh milk sourced daily from local farmers in Eastern Nepal.',
            'image' => 'https://images.unsplash.com/photo-1563636619-e9143da7973b?auto=format&fit=crop&w=600&q=80',
            'status' => 'active',
        ]);

        $milk500 = ProductVariant::create([
            'product_id' => $ddcMilk->id,
            'sku' => 'DDC-MILK-500ML',
            'barcode' => '8901234560001',
            'name' => '500ml Pouch',
            'price' => 45.00,
            'weight_kg' => 0.52,
            'cod_allowed' => true,
        ]);

        $milk1L = ProductVariant::create([
            'product_id' => $ddcMilk->id,
            'sku' => 'DDC-MILK-1000ML',
            'barcode' => '8901234560002',
            'name' => '1L Double Pouch',
            'price' => 88.00,
            'weight_kg' => 1.05,
            'cod_allowed' => true,
        ]);

        // Product 4: Amul Butter
        $amulButter = Product::create([
            'name' => 'Amul Pasteurised Pure Butter',
            'slug' => 'amul-pasteurised-pure-butter',
            'brand_id' => $brandAmul->id,
            'category_id' => $catDairy->id,
            'description' => 'Utterly Butterly Delicious pure creamy butter made with fresh cream.',
            'image' => 'https://images.unsplash.com/photo-1589985270826-4b7bb135bc9d?auto=format&fit=crop&w=600&q=80',
            'status' => 'active',
        ]);

        $butter100g = ProductVariant::create([
            'product_id' => $amulButter->id,
            'sku' => 'AMUL-BTR-100G',
            'barcode' => '8901262010052',
            'name' => '100g Carton',
            'price' => 110.00,
            'weight_kg' => 0.11,
            'cod_allowed' => true,
        ]);

        $butter500g = ProductVariant::create([
            'product_id' => $amulButter->id,
            'sku' => 'AMUL-BTR-500G',
            'barcode' => '8901262010076',
            'name' => '500g Value Pack',
            'price' => 520.00,
            'weight_kg' => 0.52,
            'cod_allowed' => true,
        ]);

        // Product 5: Britannia Good Day Butter Cookies
        $goodDay = Product::create([
            'name' => 'Britannia Good Day Butter Cookies',
            'slug' => 'britannia-good-day-butter-cookies',
            'brand_id' => $brandBritannia->id,
            'category_id' => $catSnacks->id,
            'description' => 'Rich butter cookies with a smiling design and crunchy bite.',
            'image' => 'https://images.unsplash.com/photo-1558961363-fa8fdf82db35?auto=format&fit=crop&w=600&q=80',
            'status' => 'active',
        ]);

        $cookies200g = ProductVariant::create([
            'product_id' => $goodDay->id,
            'sku' => 'BRIT-GD-200G',
            'barcode' => '8901063123456',
            'name' => '200g Pack',
            'price' => 60.00,
            'weight_kg' => 0.22,
            'cod_allowed' => true,
        ]);

        // Product 6: Lifebuoy Total 10 Handwash (Personal Care)
        $handwash = Product::create([
            'name' => 'Lifebuoy Total 10 Antibacterial Handwash',
            'slug' => 'lifebuoy-total-10-handwash',
            'brand_id' => $brandUnilever->id,
            'category_id' => $catPersonal->id,
            'description' => 'Fast germ protection handwash with Activ Silver+ formula.',
            'image' => 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?auto=format&fit=crop&w=600&q=80',
            'status' => 'active',
        ]);

        $handwash200 = ProductVariant::create([
            'product_id' => $handwash->id,
            'sku' => 'LB-HW-200ML',
            'barcode' => '8901030789012',
            'name' => '200ml Dispenser',
            'price' => 140.00,
            'weight_kg' => 0.25,
            'cod_allowed' => true,
        ]);

        // 9. Seed Inventories (§7, §21)
        // Dharan Warehouse: Plentiful stock across all products
        $dharanStock = [
            $coke250->id => 60,
            $coke500->id => 45,
            $coke15L->id => 30,
            $waiwaiSingle->id => 150,
            $waiwaiBox->id => 40,
            $milk500->id => 50,
            $milk1L->id => 35,
            $butter100g->id => 25,
            $butter500g->id => 15,
            $cookies200g->id => 75,
            $handwash200->id => 30,
        ];

        foreach ($dharanStock as $variantId => $qty) {
            Inventory::create([
                'warehouse_id' => $whDharan->id,
                'product_variant_id' => $variantId,
                'quantity' => $qty,
                'reserved_quantity' => 0,
                'reorder_level' => 10,
            ]);
        }

        // Itahari Warehouse: High stock on beverages and noodles, moderate on dairy
        $itahariStock = [
            $coke250->id => 40,
            $coke500->id => 30,
            $coke15L->id => 20,
            $waiwaiSingle->id => 120,
            $waiwaiBox->id => 30,
            $milk500->id => 20,
            $milk1L->id => 15,
            $butter100g->id => 10,
            $butter500g->id => 5,
            $cookies200g->id => 50,
            $handwash200->id => 15,
        ];

        foreach ($itahariStock as $variantId => $qty) {
            Inventory::create([
                'warehouse_id' => $whItahari->id,
                'product_variant_id' => $variantId,
                'quantity' => $qty,
                'reserved_quantity' => 0,
                'reorder_level' => 8,
            ]);
        }

        // Biratnagar Warehouse: Some items out of stock (e.g. Milk 1L, Coke 1.5L) to demonstrate city availability switch (§14)
        $biratnagarStock = [
            $coke250->id => 25,
            $coke500->id => 20,
            $coke15L->id => 0, // OUT OF STOCK IN BRT
            $waiwaiSingle->id => 80,
            $waiwaiBox->id => 15,
            $milk500->id => 15,
            $milk1L->id => 0, // OUT OF STOCK IN BRT
            $butter100g->id => 8,
            $butter500g->id => 0, // OUT OF STOCK IN BRT
            $cookies200g->id => 30,
            $handwash200->id => 20,
        ];

        foreach ($biratnagarStock as $variantId => $qty) {
            Inventory::create([
                'warehouse_id' => $whBiratnagar->id,
                'product_variant_id' => $variantId,
                'quantity' => $qty,
                'reserved_quantity' => 0,
                'reorder_level' => 5,
            ]);
        }

        // 10. City-Specific Pricing Overrides (§8)
        // In Dharan: Special promotional sale on Coke 1.5L (Rs 155 instead of 170)
        ProductPrice::create([
            'product_variant_id' => $coke15L->id,
            'city_id' => $cityDharan->id,
            'price' => 170.00,
            'sale_price' => 155.00,
            'start_at' => now()->subDay(),
            'end_at' => now()->addMonth(),
        ]);

        // In Biratnagar: Coke 500ml is slightly cheaper at Rs 80
        ProductPrice::create([
            'product_variant_id' => $coke500->id,
            'city_id' => $cityBiratnagar->id,
            'price' => 85.00,
            'sale_price' => 80.00,
            'start_at' => now()->subDay(),
            'end_at' => now()->addMonth(),
        ]);

        // 11. Seed Coupons (§31.3)
        Coupon::create([
            'code' => 'DHARANFAST',
            'city_id' => $cityDharan->id,
            'discount_type' => 'fixed',
            'discount_value' => 50.00,
            'minimum_order' => 300.00,
            'usage_limit' => 500,
            'status' => 'active',
        ]);

        Coupon::create([
            'code' => 'WELCOME10',
            'city_id' => null, // Valid in all cities
            'discount_type' => 'percent',
            'discount_value' => 10.00,
            'minimum_order' => 200.00,
            'max_discount' => 100.00,
            'usage_limit' => 1000,
            'status' => 'active',
        ]);

        // 12. Seed Delivery Agents (§23)
        $rider1 = DeliveryAgent::create([
            'name' => 'Bikram Rai',
            'phone' => '9812345670',
            'city_id' => $cityDharan->id,
            'status' => 'active',
            'availability' => 'available',
            'current_latitude' => 26.8130,
            'current_longitude' => 87.2840,
        ]);

        $rider2 = DeliveryAgent::create([
            'name' => 'Prakash Limbu',
            'phone' => '9812345671',
            'city_id' => $cityDharan->id,
            'status' => 'active',
            'availability' => 'available',
            'current_latitude' => 26.8150,
            'current_longitude' => 87.2800,
        ]);

        // 13. Seed Sample Customer Address
        $sampleAddress = Address::create([
            'user_id' => $customer->id,
            'city_id' => $cityDharan->id,
            'delivery_zone_id' => $zoneBhanuchowk->id,
            'full_name' => 'Aayush Shrestha',
            'phone' => '9842000000',
            'area' => 'Bhanuchowk',
            'street' => 'College Road, Lane 4',
            'landmark' => 'Near Clock Tower',
            'latitude' => '26.812600',
            'longitude' => '87.283500',
            'delivery_notes' => 'Please call when arriving at the gate.',
        ]);

        // 14. Seed Sample Initial Orders (to populate the warehouse Kanban dashboard)
        $sampleOrder = Order::create([
            'order_number' => 'ORD-'.strtoupper(Str::random(8)),
            'user_id' => $customer->id,
            'city_id' => $cityDharan->id,
            'warehouse_id' => $whDharan->id,
            'address_id' => $sampleAddress->id,
            'delivery_agent_id' => $rider1->id,
            'subtotal' => 320.00,
            'discount' => 50.00,
            'delivery_fee' => 30.00,
            'tax' => 0.00,
            'grand_total' => 300.00,
            'payment_method' => PaymentMethod::Cod,
            'order_status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Unpaid,
            'delivery_status' => DeliveryStatus::Assigned,
            'placed_at' => now()->subMinutes(12),
            'confirmed_at' => now()->subMinutes(10),
            'notes' => 'Deliver ASAP',
        ]);

        OrderItem::create([
            'order_id' => $sampleOrder->id,
            'product_id' => $coke->id,
            'variant_id' => $coke500->id,
            'product_name' => 'Coca-Cola Refreshing Soft Drink - 500ml Bottle',
            'sku' => $coke500->sku,
            'quantity' => 2,
            'unit_price' => 85.00,
            'discount' => 0.00,
            'tax' => 0.00,
            'total' => 170.00,
        ]);

        OrderItem::create([
            'order_id' => $sampleOrder->id,
            'product_id' => $waiwai->id,
            'variant_id' => $waiwaiBox->id,
            'product_name' => 'Wai Wai Quick Instant Noodles Chicken - Pack of 12',
            'sku' => $waiwaiBox->sku,
            'quantity' => 1,
            'unit_price' => 280.00,
            'discount' => 50.00,
            'tax' => 0.00,
            'total' => 230.00,
        ]);

        $sampleOrder->statusLogs()->create([
            'user_id' => $admin->id,
            'status_type' => 'order',
            'from_status' => OrderStatus::Pending->value,
            'to_status' => OrderStatus::Processing->value,
            'reason' => 'Order verified and assigned to Dharan Dark Store',
            'created_at' => now()->subMinutes(10),
        ]);

        AuditLog::record(
            'order_created',
            $sampleOrder,
            null,
            $sampleOrder->toArray(),
            'Initial sample order seeded',
            $admin->id
        );
    }
}
