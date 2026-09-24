<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\Catalog\CityCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        protected CityCatalogService $catalogService
    ) {}

    /**
     * City-scoped product listing (§3, §29, §38.1).
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city_id' => 'required|integer|exists:cities,id',
            'category_id' => 'nullable|integer|exists:categories,id',
            'q' => 'nullable|string|max:100',
            'in_stock_only' => 'nullable|boolean',
        ]);

        $result = $this->catalogService->getProductsForCity(
            cityId: (int) $validated['city_id'],
            categoryId: isset($validated['category_id']) ? (int) $validated['category_id'] : null,
            search: $validated['q'] ?? null,
            inStockOnly: (bool) ($validated['in_stock_only'] ?? false)
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Product details resolved for specific city.
     */
    public function show(string $slug, Request $request): JsonResponse
    {
        $request->validate([
            'city_id' => 'required|integer|exists:cities,id',
        ]);

        $cityId = (int) $request->input('city_id');
        $product = $this->catalogService->getProductDetailsForCity($slug, $cityId);

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found or unavailable in this city.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'product' => $product,
        ]);
    }

    /**
     * List all active categories.
     */
    public function categories(): JsonResponse
    {
        $categories = Category::active()
            ->withCount('products')
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'categories' => $categories,
        ]);
    }
}
