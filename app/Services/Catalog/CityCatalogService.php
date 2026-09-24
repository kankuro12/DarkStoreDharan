<?php

namespace App\Services\Catalog;

use App\Models\City;
use App\Models\Inventory;
use App\Models\Product;

class CityCatalogService
{
    /**
     * Get products available for a specific city with stock and city-specific pricing.
     */
    public function getProductsForCity(
        int $cityId,
        ?int $categoryId = null,
        ?string $search = null,
        bool $inStockOnly = false,
        ?array $productIds = null
    ): array {
        $city = City::with(['activeWarehouses'])->find($cityId);

        if (! $city) {
            return [
                'city' => null,
                'products' => [],
                'estimated_minutes' => 45,
            ];
        }

        $warehouseIds = $city->activeWarehouses->pluck('id')->toArray();

        if (empty($warehouseIds)) {
            return [
                'city' => $city,
                'products' => [],
                'estimated_minutes' => $city->estimated_delivery_minutes,
            ];
        }

        $query = Product::with(['category', 'brand', 'variants.prices'])
            ->where('status', 'active');

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($productIds !== null) {
            $query->whereIn('id', $productIds);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('variants', function ($vq) use ($search) {
                        $vq->where('sku', 'like', "%{$search}%")
                            ->orWhere('barcode', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        $products = $query->get();

        // Load inventory totals for all variants across the city's active warehouses
        $variantInventories = Inventory::whereIn('warehouse_id', $warehouseIds)
            ->get()
            ->groupBy('product_variant_id');

        $result = [];

        foreach ($products as $product) {
            $variantPayloads = [];
            $productHasStock = false;

            foreach ($product->variants->where('status', 'active') as $variant) {
                $inventories = $variantInventories->get($variant->id, collect());
                $totalAvailable = $inventories->sum(fn ($inv) => $inv->available);

                if ($totalAvailable > 0) {
                    $productHasStock = true;
                }

                $priceData = $variant->getPriceForCity($cityId);

                $variantPayloads[] = [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'name' => $variant->name,
                    'price' => $priceData['price'],
                    'regular_price' => $priceData['regular_price'],
                    'is_sale' => $priceData['is_sale'],
                    'weight_kg' => $variant->weight_kg,
                    'available_stock' => $totalAvailable,
                    'is_available' => $totalAvailable > 0,
                    'cod_allowed' => $variant->cod_allowed,
                ];
            }

            if ($inStockOnly && ! $productHasStock) {
                continue;
            }

            $primaryVariant = $variantPayloads[0] ?? null;

            $result[] = [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'image' => $product->image,
                'description' => $product->description,
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                    'slug' => $product->category->slug,
                ] : null,
                'brand' => $product->brand ? [
                    'id' => $product->brand->id,
                    'name' => $product->brand->name,
                ] : null,
                'is_available' => $productHasStock,
                'primary_price' => $primaryVariant['price'] ?? 0,
                'primary_regular_price' => $primaryVariant['regular_price'] ?? 0,
                'is_sale' => $primaryVariant['is_sale'] ?? false,
                'estimated_minutes' => $city->estimated_delivery_minutes,
                'variants' => $variantPayloads,
            ];
        }

        return [
            'city' => [
                'id' => $city->id,
                'name' => $city->name,
                'slug' => $city->slug,
                'default_delivery_fee' => (float) $city->default_delivery_fee,
                'free_delivery_minimum' => (float) $city->free_delivery_minimum,
                'estimated_minutes' => $city->estimated_delivery_minutes,
            ],
            'products' => $result,
            'total' => count($result),
        ];
    }

    /**
     * Get single product details resolved for a city.
     */
    public function getProductDetailsForCity(string $slug, int $cityId): ?array
    {
        $city = City::with(['activeWarehouses'])->find($cityId);
        if (! $city) {
            return null;
        }

        $warehouseIds = $city->activeWarehouses->pluck('id')->toArray();

        $product = Product::with(['category', 'brand', 'variants.prices'])
            ->where('slug', $slug)
            ->where('status', 'active')
            ->first();

        if (! $product) {
            return null;
        }

        $variantInventories = Inventory::whereIn('warehouse_id', $warehouseIds)
            ->get()
            ->groupBy('product_variant_id');

        $variantPayloads = [];
        $productHasStock = false;

        foreach ($product->variants->where('status', 'active') as $variant) {
            $inventories = $variantInventories->get($variant->id, collect());
            $totalAvailable = $inventories->sum(fn ($inv) => $inv->available);

            if ($totalAvailable > 0) {
                $productHasStock = true;
            }

            $priceData = $variant->getPriceForCity($cityId);

            $variantPayloads[] = [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'barcode' => $variant->barcode,
                'name' => $variant->name,
                'price' => $priceData['price'],
                'regular_price' => $priceData['regular_price'],
                'is_sale' => $priceData['is_sale'],
                'weight_kg' => $variant->weight_kg,
                'available_stock' => $totalAvailable,
                'is_available' => $totalAvailable > 0,
                'cod_allowed' => $variant->cod_allowed,
            ];
        }

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'image' => $product->image,
            'description' => $product->description,
            'category' => $product->category,
            'brand' => $product->brand,
            'is_available' => $productHasStock,
            'city_name' => $city->name,
            'estimated_minutes' => $city->estimated_delivery_minutes,
            'variants' => $variantPayloads,
        ];
    }
}
