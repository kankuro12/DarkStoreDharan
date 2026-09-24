<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\City;
use App\Services\Catalog\CityCatalogService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(
        protected CityCatalogService $catalogService
    ) {}

    public function index(Request $request): View
    {
        $allCities = City::active()->get();

        // Retrieve selected city from session or request parameter.
        // No default city is chosen - customer must choose city! (§3, §38.5)
        $selectedCityId = $request->query('city_id', session('cart.city_id'));

        if ($selectedCityId && $allCities->contains('id', (int) $selectedCityId)) {
            $selectedCityId = (int) $selectedCityId;
            session(['cart.city_id' => $selectedCityId]);
            $currentCity = $allCities->firstWhere('id', $selectedCityId);
        } else {
            $selectedCityId = null;
            $currentCity = null;
            session()->forget('cart.city_id');
        }

        $selectedCategoryId = $request->query('category_id') ? (int) $request->query('category_id') : null;
        $search = $request->query('q');

        $categories = Category::active()->withCount('products')->orderBy('sort_order')->get();

        if ($currentCity) {
            $catalog = $this->catalogService->getProductsForCity(
                cityId: $selectedCityId,
                categoryId: $selectedCategoryId,
                search: $search
            );
            $products = $catalog['products'];
            
            $featuredProducts = [];
            if (!empty($currentCity->featured_products)) {
                $featuredCatalog = $this->catalogService->getProductsForCity(
                    cityId: $selectedCityId,
                    inStockOnly: false,
                    productIds: $currentCity->featured_products
                );
                $featuredProducts = $featuredCatalog['products'] ?? [];
            }
            
            $estimatedMinutes = $catalog['city']['estimated_minutes'] ?? ($currentCity->estimated_delivery_minutes ?? 30);
        } else {
            $products = [];
            $featuredProducts = [];
            $estimatedMinutes = null;
        }

        return view('storefront.home', [
            'cities' => $allCities,
            'currentCity' => $currentCity,
            'categories' => $categories,
            'selectedCategoryId' => $selectedCategoryId,
            'search' => $search,
            'products' => $products,
            'featuredProducts' => $featuredProducts,
            'banners' => $currentCity->banners ?? [],
            'estimatedMinutes' => $estimatedMinutes,
        ]);
    }

    public function product(string $slug, Request $request)
    {
        $allCities = City::active()->get();
        $selectedCityId = $request->query('city_id', session('cart.city_id'));

        // If no city has been selected, redirect to city picker on home with guidance (§3, §38.5)
        if (! $selectedCityId || ! $allCities->contains('id', (int) $selectedCityId)) {
            return redirect()->route('storefront.home')
                ->with('info', 'Please select your delivery city first to check product availability and pricing.');
        }

        $selectedCityId = (int) $selectedCityId;
        $currentCity = $allCities->firstWhere('id', $selectedCityId);

        $product = $this->catalogService->getProductDetailsForCity($slug, $selectedCityId);

        if (! $product) {
            abort(404, 'Product not found or unavailable in this city.');
        }

        $relatedProducts = [];
        if (! empty($product['category']['id'])) {
            $catCatalog = $this->catalogService->getProductsForCity(
                cityId: $selectedCityId,
                categoryId: (int) $product['category']['id'],
                inStockOnly: false
            );
            $relatedProducts = collect($catCatalog['products'] ?? [])
                ->where('slug', '!=', $slug)
                ->take(4)
                ->values()
                ->all();
        }

        return view('storefront.product', [
            'cities' => $allCities,
            'currentCity' => $currentCity,
            'product' => $product,
            'relatedProducts' => $relatedProducts,
        ]);
    }
}
