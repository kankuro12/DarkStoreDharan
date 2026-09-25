<?php

use App\Models\City;
use App\Models\Product;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$city = City::find(1);
$products = Product::take(4)->pluck('id')->toArray();
$city->update([
    'featured_products' => $products,
    'banners' => [
        ['image_url' => 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&q=80&w=1200&h=400', 'link_url' => ''],
        ['image_url' => 'https://images.unsplash.com/photo-1604719312566-8912e9227c6a?auto=format&fit=crop&q=80&w=1200&h=400', 'link_url' => ''],
    ],
]);
echo "City 1 Updated\n";
