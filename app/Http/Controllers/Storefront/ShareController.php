<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Catalog\CityCatalogService;

class ShareController extends Controller
{
    public function product(string $slug, CityCatalogService $catalogService)
    {
        // For sharing, we don't necessarily have a city.
        // We can just fetch the product directly from the DB for meta info.
        $product = Product::where('slug', $slug)->active()->firstOrFail();

        return view('storefront.share.product', [
            'product' => $product,
            'targetUrl' => route('storefront.product', $slug),
        ]);
    }
}
