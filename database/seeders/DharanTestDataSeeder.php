<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\Coupon;
use App\Models\DeliveryZone;
use App\Models\FreeDeliveryRule;
use App\Models\Inventory;
use App\Models\Popup;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Test dataset for the Dharan storefront: city, warehouse, zones, categories,
 * products/variants, stock, a few city prices, coupons and homepage content.
 *
 * Idempotent - safe to run more than once. Images are downloaded once into the
 * public disk and self-hosted (falls back to the remote URL for catalogue art).
 *
 *   php artisan db:seed --class=DharanTestDataSeeder --force
 */
class DharanTestDataSeeder extends Seeder
{
    public function run(): void
    {
        $city = $this->seedCity();
        $warehouse = $this->seedWarehouse($city);
        $this->seedZones($city);
        $categories = $this->seedCategories();
        $brands = $this->seedBrands();

        $products = $this->seedProducts($categories, $brands, $warehouse, $city);

        $this->seedCoupons($city);
        $this->seedFreeDelivery($city);
        $this->seedPopup();
        $this->seedHomepage($city, $products);

        $this->command?->info('Dharan test dataset ready: '.count($products).' products, '.count($categories).' categories.');
    }

    private function seedCity(): City
    {
        return City::updateOrCreate(
            ['slug' => 'dharan'],
            [
                'name' => 'Dharan',
                'province' => 'Koshi Province',
                'status' => 'active',
                'cod_enabled' => true,
                'prepaid_enabled' => true,
                'default_delivery_fee' => 40.00,
                'free_delivery_minimum' => 1000.00,
                'estimated_delivery_minutes' => 30,
            ]
        );
    }

    private function seedWarehouse(City $city): Warehouse
    {
        $warehouse = Warehouse::updateOrCreate(
            ['code' => 'WH-DHN-01'],
            [
                'name' => 'Dharan Central Dark Store',
                'address' => 'Putali Line, Ward 3, Dharan',
                'latitude' => 26.8124000,
                'longitude' => 87.2834000,
                'status' => 'active',
            ]
        );

        $pivot = ['priority' => 1, 'delivery_minutes' => 30, 'delivery_fee' => 40.00, 'status' => 'active'];

        if ($city->warehouses()->whereKey($warehouse->id)->exists()) {
            $city->warehouses()->updateExistingPivot($warehouse->id, $pivot);
        } else {
            $city->warehouses()->attach($warehouse->id, $pivot);
        }

        return $warehouse;
    }

    private function seedZones(City $city): void
    {
        $zones = [
            ['name' => 'Bhanuchowk Central', 'postal_code' => '56700', 'radius_km' => 3.0, 'delivery_fee' => 30.00, 'minimum_order' => 100.00, 'estimated_minutes' => 20],
            ['name' => 'Pindeshwor / BPKIHS Area', 'postal_code' => '56700', 'radius_km' => 6.0, 'delivery_fee' => 45.00, 'minimum_order' => 150.00, 'estimated_minutes' => 35],
            ['name' => 'Chatara Line / Ghopa', 'postal_code' => '56700', 'radius_km' => 8.0, 'delivery_fee' => 50.00, 'minimum_order' => 200.00, 'estimated_minutes' => 40],
        ];

        foreach ($zones as $zone) {
            DeliveryZone::updateOrCreate(
                ['city_id' => $city->id, 'name' => $zone['name']],
                $zone + ['city_id' => $city->id, 'cod_enabled' => true, 'status' => 'active']
            );
        }
    }

    /**
     * @return array<string, Category>
     */
    private function seedCategories(): array
    {
        $tree = [
            'groceries-staples' => ['name' => 'Groceries & Staples', 'sort' => 1, 'img' => ['categories/groceries-staples.jpg', 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=800&q=70']],
            'rice-grains' => ['name' => 'Rice & Grains', 'sort' => 1, 'parent' => 'groceries-staples', 'img' => ['categories/rice-grains.jpg', 'https://picsum.photos/seed/darkstore-rice-grains/800/800']],
            'cooking-oil-spices' => ['name' => 'Cooking Oil & Spices', 'sort' => 2, 'parent' => 'groceries-staples', 'img' => ['categories/cooking-oil-spices.jpg', 'https://picsum.photos/seed/darkstore-oil-spices/800/800']],
            'dairy-breakfast' => ['name' => 'Dairy & Breakfast', 'sort' => 2, 'img' => ['categories/dairy-breakfast.jpg', 'https://images.unsplash.com/photo-1550583724-b2692b85b150?auto=format&fit=crop&w=800&q=70']],
            'milk-curd' => ['name' => 'Milk & Curd', 'sort' => 1, 'parent' => 'dairy-breakfast', 'img' => ['categories/milk-curd.jpg', 'https://images.unsplash.com/photo-1563636619-e9143da7973b?auto=format&fit=crop&w=800&q=70']],
            'snacks-beverages' => ['name' => 'Snacks & Beverages', 'sort' => 3, 'img' => ['categories/snacks-beverages.jpg', 'https://images.unsplash.com/photo-1563729784474-d77dbb933a9e?auto=format&fit=crop&w=800&q=70']],
            'cold-drinks' => ['name' => 'Cold Drinks', 'sort' => 1, 'parent' => 'snacks-beverages', 'img' => ['categories/cold-drinks.jpg', 'https://images.unsplash.com/photo-1622483767028-3f66f32aef97?auto=format&fit=crop&w=800&q=70']],
            'instant-noodles' => ['name' => 'Instant Noodles', 'sort' => 2, 'parent' => 'snacks-beverages', 'img' => ['categories/instant-noodles.jpg', 'https://images.unsplash.com/photo-1612927601601-6638404737ce?auto=format&fit=crop&w=800&q=70']],
            'personal-care' => ['name' => 'Personal Care & Hygiene', 'sort' => 4, 'img' => ['categories/personal-care.jpg', 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?auto=format&fit=crop&w=800&q=70']],
            'household-essentials' => ['name' => 'Household Essentials', 'sort' => 5, 'img' => ['categories/household-essentials.jpg', 'https://picsum.photos/seed/darkstore-household/800/800']],
        ];

        $categories = [];

        foreach ($tree as $slug => $meta) {
            $categories[$slug] = Category::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $meta['name'],
                    'parent_id' => isset($meta['parent']) ? ($categories[$meta['parent']]->id ?? null) : null,
                    'image' => $this->media($meta['img'][0], $meta['img'][1]),
                    'sort_order' => $meta['sort'],
                    'status' => 'active',
                ]
            );
        }

        return $categories;
    }

    /**
     * @return array<string, Brand>
     */
    private function seedBrands(): array
    {
        $brands = [];

        foreach ([
            'coca-cola' => 'Coca-Cola',
            'wai-wai' => 'Wai Wai',
            'ddc-nepal' => 'DDC Nepal',
            'amul' => 'Amul',
            'britannia' => 'Britannia',
            'unilever' => 'Unilever',
            'sampurna' => 'Sampurna',
            'fortune' => 'Fortune',
        ] as $slug => $name) {
            $brands[$slug] = Brand::updateOrCreate(['slug' => $slug], ['name' => $name, 'status' => 'active']);
        }

        return $brands;
    }

    /**
     * @param  array<string, Category>  $categories
     * @param  array<string, Brand>  $brands
     * @return array<string, Product>
     */
    private function seedProducts(array $categories, array $brands, Warehouse $warehouse, City $city): array
    {
        $catalog = [
            'coca-cola-refreshing-soft-drink' => [
                'name' => 'Coca-Cola Refreshing Soft Drink',
                'brand' => 'coca-cola',
                'category' => 'cold-drinks',
                'description' => 'Original Taste Coca-Cola sparkling soda. Chilled and delivered in 30 minutes.',
                'img' => ['products/coca-cola.jpg', 'https://images.unsplash.com/photo-1622483767028-3f66f32aef97?auto=format&fit=crop&w=800&q=70'],
                'variants' => [
                    ['COKE-CAN-250ML', '250ml Can', 65.00, 0.28, 60],
                    ['COKE-PET-500ML', '500ml Bottle', 85.00, 0.55, 45],
                    ['COKE-PET-1500ML', '1.5L Family Pack', 170.00, 1.60, 30],
                ],
                'sale' => ['COKE-PET-1500ML', 170.00, 155.00],
            ],
            'wai-wai-quick-chicken-noodles' => [
                'name' => 'Wai Wai Quick Instant Noodles Chicken',
                'brand' => 'wai-wai',
                'category' => 'instant-noodles',
                'description' => 'Iconic ready-to-eat spiced chicken noodles. Authentic Nepali favourite.',
                'img' => ['products/wai-wai.jpg', 'https://images.unsplash.com/photo-1612927601601-6638404737ce?auto=format&fit=crop&w=800&q=70'],
                'variants' => [
                    ['WAIWAI-CHK-75G', '75g Single Pack', 25.00, 0.08, 150],
                    ['WAIWAI-CHK-BOX12', 'Pack of 12 (Special Save)', 280.00, 0.95, 40],
                ],
                'sale' => ['WAIWAI-CHK-BOX12', 280.00, 260.00],
            ],
            'ddc-standard-fresh-milk' => [
                'name' => 'DDC Standard Pasteurized Fresh Milk',
                'brand' => 'ddc-nepal',
                'category' => 'milk-curd',
                'description' => 'Cold-chain pasteurized fresh milk sourced daily from Eastern Nepal farmers.',
                'img' => ['products/ddc-milk.jpg', 'https://images.unsplash.com/photo-1563636619-e9143da7973b?auto=format&fit=crop&w=800&q=70'],
                'variants' => [
                    ['DDC-MILK-500ML', '500ml Pouch', 45.00, 0.52, 50],
                    ['DDC-MILK-1000ML', '1L Double Pouch', 88.00, 1.05, 35],
                ],
            ],
            'amul-pasteurised-pure-butter' => [
                'name' => 'Amul Pasteurised Pure Butter',
                'brand' => 'amul',
                'category' => 'dairy-breakfast',
                'description' => 'Creamy pure butter made with fresh cream.',
                'img' => ['products/amul-butter.jpg', 'https://images.unsplash.com/photo-1589985270826-4b7bb135bc9d?auto=format&fit=crop&w=800&q=70'],
                'variants' => [
                    ['AMUL-BTR-100G', '100g Carton', 110.00, 0.11, 25],
                    ['AMUL-BTR-500G', '500g Value Pack', 520.00, 0.52, 15],
                ],
            ],
            'britannia-good-day-butter-cookies' => [
                'name' => 'Britannia Good Day Butter Cookies',
                'brand' => 'britannia',
                'category' => 'snacks-beverages',
                'description' => 'Rich butter cookies with a crunchy bite.',
                'img' => ['products/good-day-cookies.jpg', 'https://images.unsplash.com/photo-1558961363-fa8fdf82db35?auto=format&fit=crop&w=800&q=70'],
                'variants' => [
                    ['BRIT-GD-200G', '200g Pack', 60.00, 0.22, 75],
                ],
            ],
            'lifebuoy-total-10-handwash' => [
                'name' => 'Lifebuoy Total 10 Antibacterial Handwash',
                'brand' => 'unilever',
                'category' => 'personal-care',
                'description' => 'Fast germ protection handwash with Activ Silver+ formula.',
                'img' => ['products/lifebuoy-handwash.jpg', 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?auto=format&fit=crop&w=800&q=70'],
                'variants' => [
                    ['LB-HW-200ML', '200ml Dispenser', 140.00, 0.25, 30],
                ],
            ],
            'sampurna-basmati-rice' => [
                'name' => 'Sampurna Basmati Rice',
                'brand' => 'sampurna',
                'category' => 'rice-grains',
                'description' => 'Long-grain aromatic basmati rice, cleaned and sorted.',
                'img' => ['products/sampurna-rice.jpg', 'https://picsum.photos/seed/darkstore-rice/800/800'],
                'variants' => [
                    ['SAMP-RICE-1KG', '1kg Bag', 145.00, 1.00, 60],
                    ['SAMP-RICE-5KG', '5kg Bag', 690.00, 5.00, 25],
                ],
            ],
            'fortune-sunflower-oil' => [
                'name' => 'Fortune Refined Sunflower Oil',
                'brand' => 'fortune',
                'category' => 'cooking-oil-spices',
                'description' => 'Light, healthy refined sunflower oil for everyday cooking.',
                'img' => ['products/fortune-oil.jpg', 'https://picsum.photos/seed/darkstore-oil/800/800'],
                'variants' => [
                    ['FORT-OIL-1L', '1L Pouch', 230.00, 1.00, 40],
                    ['FORT-OIL-5L', '5L Jar', 1120.00, 5.00, 12],
                ],
            ],
            'real-fruit-juice-orange' => [
                'name' => 'Real Fruit Juice Orange',
                'brand' => 'coca-cola',
                'category' => 'cold-drinks',
                'description' => 'Refreshing orange fruit drink, ready to serve.',
                'img' => ['products/real-juice.jpg', 'https://picsum.photos/seed/darkstore-juice/800/800'],
                'variants' => [
                    ['REAL-JUICE-1L', '1L Tetra Pack', 180.00, 1.05, 24],
                ],
            ],
            'surf-excel-detergent-powder' => [
                'name' => 'Surf Excel Easy Wash Detergent Powder',
                'brand' => 'unilever',
                'category' => 'household-essentials',
                'description' => 'Tough stain removal for everyday laundry.',
                'img' => ['products/surf-excel.jpg', 'https://picsum.photos/seed/darkstore-detergent/800/800'],
                'variants' => [
                    ['SURF-EW-1KG', '1kg Pack', 210.00, 1.00, 20],
                ],
            ],
        ];

        $products = [];

        foreach ($catalog as $slug => $data) {
            $product = Product::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $data['name'],
                    'brand_id' => $brands[$data['brand']]->id,
                    'category_id' => $categories[$data['category']]->id,
                    'description' => $data['description'],
                    'image' => $this->media($data['img'][0], $data['img'][1]),
                    'status' => 'active',
                ]
            );
            $products[$slug] = $product;

            foreach ($data['variants'] as [$sku, $name, $price, $weight, $qty]) {
                $variant = ProductVariant::updateOrCreate(
                    ['sku' => $sku],
                    [
                        'product_id' => $product->id,
                        'name' => $name,
                        'price' => $price,
                        'weight_kg' => $weight,
                        'cod_allowed' => true,
                        'status' => 'active',
                    ]
                );

                Inventory::updateOrCreate(
                    ['warehouse_id' => $warehouse->id, 'product_variant_id' => $variant->id],
                    ['quantity' => $qty, 'reorder_level' => 10]
                );
            }

            if (isset($data['sale'])) {
                [$saleSku, $regular, $sale] = $data['sale'];
                $variantId = ProductVariant::where('sku', $saleSku)->value('id');

                ProductPrice::where('city_id', $city->id)
                    ->where('product_variant_id', $variantId)
                    ->delete();

                ProductPrice::create([
                    'product_variant_id' => $variantId,
                    'city_id' => $city->id,
                    'price' => $regular,
                    'sale_price' => $sale,
                    'start_at' => now()->subDay(),
                    'end_at' => now()->addMonth(),
                ]);
            }
        }

        return $products;
    }

    private function seedCoupons(City $city): void
    {
        Coupon::updateOrCreate(['code' => 'DHARANFAST'], [
            'city_id' => $city->id,
            'discount_type' => 'fixed',
            'discount_value' => 50.00,
            'minimum_order' => 300.00,
            'usage_limit' => 500,
            'status' => 'active',
        ]);

        Coupon::updateOrCreate(['code' => 'WELCOME10'], [
            'city_id' => null,
            'discount_type' => 'percent',
            'discount_value' => 10.00,
            'minimum_order' => 200.00,
            'max_discount' => 100.00,
            'usage_limit' => 1000,
            'status' => 'active',
        ]);
    }

    private function seedFreeDelivery(City $city): void
    {
        FreeDeliveryRule::updateOrCreate(
            ['city_id' => $city->id, 'name' => 'Dharan free delivery over Rs. 1000'],
            ['min_subtotal' => 1000.00, 'active' => true]
        );
    }

    private function seedPopup(): void
    {
        if (! Schema::hasTable('popups')) {
            return;
        }

        $image = null;
        $this->download('popups/welcome.jpg', 'https://picsum.photos/seed/darkstore-welcome/900/500');
        if (Storage::disk('public')->exists('popups/welcome.jpg')) {
            $image = 'popups/welcome.jpg';
        }

        Popup::updateOrCreate(['title' => 'Welcome to DarkStore Dharan'], [
            'body' => 'Fresh groceries delivered in 30 minutes. Free delivery on orders over Rs. 1000.',
            'image' => $image,
            'button_label' => 'Start shopping',
            'button_url' => '/',
            'placement' => 'home',
            'frequency' => 'once',
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, Product>  $products
     */
    private function seedHomepage(City $city, array $products): void
    {
        $featured = collect([
            'coca-cola-refreshing-soft-drink',
            'wai-wai-quick-chicken-noodles',
            'ddc-standard-fresh-milk',
            'amul-pasteurised-pure-butter',
        ])->map(fn (string $slug) => $products[$slug]->id ?? null)->filter()->values()->all();

        $city->update([
            'featured_products' => $featured,
            'banners' => [
                [
                    'image_url' => $this->storageUrl('banners/hero-1.jpg', 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=1200&h=400&q=70'),
                    'link_url' => '',
                ],
                [
                    'image_url' => $this->storageUrl('banners/hero-2.jpg', 'https://images.unsplash.com/photo-1604719312566-8912e9227c6a?auto=format&fit=crop&w=1200&h=400&q=70'),
                    'link_url' => '',
                ],
            ],
        ]);
    }

    /** Return a relative path if the image was downloaded, else the remote URL. */
    private function media(string $relativePath, string $url): string
    {
        $this->download($relativePath, $url);

        return Storage::disk('public')->exists($relativePath) ? $relativePath : $url;
    }

    /** Root-relative /storage URL if downloaded, else the remote URL. */
    private function storageUrl(string $relativePath, string $url): string
    {
        $this->download($relativePath, $url);

        return Storage::disk('public')->exists($relativePath) ? '/storage/'.$relativePath : $url;
    }

    private function download(string $relativePath, string $url): void
    {
        if (Storage::disk('public')->exists($relativePath)) {
            return;
        }

        $bytes = $this->fetch($url);

        if ($bytes !== null) {
            Storage::disk('public')->put($relativePath, $bytes);
        }
    }

    private function fetch(string $url): ?string
    {
        if (! function_exists('curl_init')) {
            $bytes = @file_get_contents($url);

            return is_string($bytes) ? $bytes : null;
        }

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'DarkStoreSeeder/1.0',
        ]);

        $bytes = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return (is_string($bytes) && $status >= 200 && $status < 300 && $bytes !== '') ? $bytes : null;
    }
}
