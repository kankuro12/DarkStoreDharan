<?php

namespace App\Services\Cart;

use App\Models\Address;
use App\Models\City;
use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Services\Payment\PaymentMethodService;
use App\Services\Pricing\PricingService;
use Illuminate\Support\Facades\Session;

class CartService
{
    public function __construct(
        protected PricingService $pricingService,
        protected PaymentMethodService $paymentMethodService
    ) {}

    /**
     * Get raw cart items from session.
     * Format: [variant_id => ['variant_id' => int, 'quantity' => int]]
     */
    public function getItems(): array
    {
        return Session::get('cart.items', []);
    }

    public function getSelectedCityId(): ?int
    {
        return Session::get('cart.city_id');
    }

    public function setSelectedCityId(int $cityId): void
    {
        Session::put('cart.city_id', $cityId);
    }

    public function getAppliedCoupon(): ?string
    {
        return Session::get('cart.coupon_code');
    }

    /**
     * Add item to cart with stock validation against city's warehouses.
     */
    public function addItem(int $variantId, int $quantity, int $cityId): array
    {
        $this->setSelectedCityId($cityId);
        $items = $this->getItems();

        $currentQty = isset($items[$variantId]) ? (int) $items[$variantId]['quantity'] : 0;
        $newQty = $currentQty + $quantity;

        // Check if sufficient stock exists in the city's warehouses
        $city = City::with('activeWarehouses')->findOrFail($cityId);
        $warehouseIds = $city->activeWarehouses->pluck('id')->toArray();

        $totalAvailable = Inventory::whereIn('warehouse_id', $warehouseIds)
            ->where('product_variant_id', $variantId)
            ->get()
            ->sum(fn ($inv) => $inv->available);

        if ($totalAvailable < $newQty) {
            return [
                'success' => false,
                'message' => 'Only '.$totalAvailable.' item(s) available in '.$city->name,
                'available' => $totalAvailable,
            ];
        }

        $items[$variantId] = [
            'variant_id' => $variantId,
            'quantity' => $newQty,
        ];

        Session::put('cart.items', $items);

        return [
            'success' => true,
            'cart_count' => $this->getItemCount(),
            'message' => 'Item added to cart.',
        ];
    }

    /**
     * Update item quantity.
     */
    public function updateQuantity(int $variantId, int $quantity): array
    {
        $items = $this->getItems();

        if ($quantity <= 0) {
            return $this->removeItem($variantId);
        }

        $cityId = $this->getSelectedCityId();
        if ($cityId) {
            $city = City::with('activeWarehouses')->find($cityId);
            $warehouseIds = $city ? $city->activeWarehouses->pluck('id')->toArray() : [];

            $totalAvailable = Inventory::whereIn('warehouse_id', $warehouseIds)
                ->where('product_variant_id', $variantId)
                ->get()
                ->sum(fn ($inv) => $inv->available);

            if ($totalAvailable < $quantity) {
                return [
                    'success' => false,
                    'message' => "Only {$totalAvailable} units available in {$city->name}.",
                    'available' => $totalAvailable,
                ];
            }
        }

        $items[$variantId] = [
            'variant_id' => $variantId,
            'quantity' => $quantity,
        ];

        Session::put('cart.items', $items);

        return [
            'success' => true,
            'cart_count' => $this->getItemCount(),
        ];
    }

    /**
     * Remove item from cart.
     */
    public function removeItem(int $variantId): array
    {
        $items = $this->getItems();
        unset($items[$variantId]);
        Session::put('cart.items', $items);

        return [
            'success' => true,
            'cart_count' => $this->getItemCount(),
        ];
    }

    public function clear(): void
    {
        Session::forget('cart.items');
        Session::forget('cart.coupon_code');
    }

    public function getItemCount(): int
    {
        $items = $this->getItems();

        return (int) array_sum(array_column($items, 'quantity'));
    }

    public function applyCoupon(string $code): void
    {
        Session::put('cart.coupon_code', strtoupper(trim($code)));
    }

    public function removeCoupon(): void
    {
        Session::forget('cart.coupon_code');
    }

    /**
     * Handle City Change Behavior (§14, §38.5).
     * Revalidates stock and pricing for the new city without silent removal.
     */
    public function changeCity(int $newCityId): array
    {
        $oldCityId = $this->getSelectedCityId();
        $this->setSelectedCityId($newCityId);

        $newCity = City::with('activeWarehouses')->findOrFail($newCityId);
        $warehouseIds = $newCity->activeWarehouses->pluck('id')->toArray();

        $items = $this->getItems();
        if (empty($items)) {
            return [
                'city' => $newCity,
                'unavailable_items' => [],
                'price_changed_items' => [],
                'has_changes' => false,
                'message' => "City changed to {$newCity->name}.",
            ];
        }

        $unavailableItems = [];
        $priceChangedItems = [];
        $validItems = [];

        foreach ($items as $variantId => $item) {
            $variant = ProductVariant::with('product')->find($variantId);
            if (! $variant) {
                unset($items[$variantId]);

                continue;
            }

            $qty = (int) $item['quantity'];

            // Check stock in new city
            $totalAvailable = Inventory::whereIn('warehouse_id', $warehouseIds)
                ->where('product_variant_id', $variantId)
                ->get()
                ->sum(fn ($inv) => $inv->available);

            if ($totalAvailable < $qty) {
                $unavailableItems[] = [
                    'variant_id' => $variant->id,
                    'name' => $variant->product->name.' ('.$variant->name.')',
                    'requested_quantity' => $qty,
                    'available_in_new_city' => $totalAvailable,
                ];

                if ($totalAvailable > 0) {
                    // Reduce to available quantity
                    $items[$variantId]['quantity'] = $totalAvailable;
                    $validItems[$variantId] = $items[$variantId];
                } else {
                    // Remove from cart and notify customer
                    unset($items[$variantId]);
                }
            } else {
                $validItems[$variantId] = $item;
            }

            // Check price differences
            if ($oldCityId) {
                $oldPrice = $variant->getPriceForCity($oldCityId)['price'];
                $newPrice = $variant->getPriceForCity($newCityId)['price'];

                if ($oldPrice !== $newPrice) {
                    $priceChangedItems[] = [
                        'variant_id' => $variant->id,
                        'name' => $variant->product->name,
                        'old_price' => $oldPrice,
                        'new_price' => $newPrice,
                    ];
                }
            }
        }

        Session::put('cart.items', $items);

        $hasChanges = ! empty($unavailableItems) || ! empty($priceChangedItems);
        $message = $hasChanges
            ? "Your cart has changed because some products or prices are different in {$newCity->name}."
            : "City successfully changed to {$newCity->name}.";

        return [
            'city' => $newCity,
            'unavailable_items' => $unavailableItems,
            'price_changed_items' => $priceChangedItems,
            'has_changes' => $hasChanges,
            'message' => $message,
            'items' => $this->getDetailedCart($newCityId),
        ];
    }

    /**
     * Get detailed cart with prices and totals for rendering.
     */
    public function getDetailedCart(?int $cityId = null): array
    {
        $cityId = $cityId ?? $this->getSelectedCityId();

        if (! $cityId) {
            return [
                'city' => null,
                'items' => [],
                'item_count' => 0,
                'subtotal' => 0.0,
                'discount' => 0.0,
                'delivery_fee' => 0.0,
                'grand_total' => 0.0,
            ];
        }

        $rawItems = array_values($this->getItems());
        if (empty($rawItems)) {
            $city = City::find($cityId);

            return [
                'city' => $city,
                'items' => [],
                'item_count' => 0,
                'subtotal' => 0.0,
                'discount' => 0.0,
                'delivery_fee' => (float) ($city?->default_delivery_fee ?? 0),
                'grand_total' => 0.0,
            ];
        }

        $couponCode = $this->getAppliedCoupon();
        $pricing = $this->pricingService->calculate($cityId, $rawItems, $couponCode);

        // Attach product model images to item details for UI rendering
        foreach ($pricing['items'] as &$item) {
            $variant = ProductVariant::with('product')->find($item['variant_id']);
            $item['image'] = $variant?->product?->image;
            $item['slug'] = $variant?->product?->slug;
        }

        $city = City::find($cityId);

        return [
            'city' => $city,
            'items' => $pricing['items'],
            'item_count' => $this->getItemCount(),
            'subtotal' => $pricing['subtotal'],
            'discount' => $pricing['discount'],
            'delivery_fee' => $pricing['delivery_fee'],
            'tax' => $pricing['tax'],
            'grand_total' => $pricing['grand_total'],
            'coupon' => $couponCode,
            'free_delivery_applied' => $pricing['free_delivery_applied'],
            'estimated_minutes' => $city?->estimated_delivery_minutes ?? 45,
        ];
    }

    /**
     * Validate cart for checkout per Section 30 API specification (§30).
     */
    public function validateCheckout(int $cityId, ?int $addressId = null, ?array $overrideItems = null): array
    {
        $city = City::with('activeWarehouses')->findOrFail($cityId);
        $address = $addressId ? Address::with('deliveryZone')->find($addressId) : null;
        $zoneId = $address?->delivery_zone_id;

        $rawItems = $overrideItems ?? array_values($this->getItems());

        $warehouseIds = $city->activeWarehouses->pluck('id')->toArray();
        $couponCode = $this->getAppliedCoupon();

        $pricing = $this->pricingService->calculate($cityId, $rawItems, $couponCode, $zoneId);

        // Enrich items with availability against active warehouses
        $validatedItems = [];
        $allAvailable = true;

        foreach ($pricing['items'] as $item) {
            $variantId = $item['variant_id'];
            $reqQty = $item['quantity'];

            $availableInCity = Inventory::whereIn('warehouse_id', $warehouseIds)
                ->where('product_variant_id', $variantId)
                ->get()
                ->sum(fn ($inv) => $inv->available);

            $isAvailable = $availableInCity >= $reqQty;
            if (! $isAvailable) {
                $allAvailable = false;
            }

            $validatedItems[] = [
                'variant_id' => $variantId,
                'sku' => $item['sku'],
                'name' => $item['product_name'],
                'quantity' => $reqQty,
                'available' => $isAvailable,
                'available_quantity' => $availableInCity,
                'price' => $item['unit_price'],
                'total' => $item['total'],
            ];
        }

        // Resolve available payment methods
        $paymentMethods = $this->paymentMethodService->resolveAvailableMethods(
            $cityId,
            $pricing['grand_total'],
            $rawItems,
            auth()->user(),
            $address
        );

        $estMin = $address?->deliveryZone?->estimated_minutes ?? $city->estimated_delivery_minutes;

        return [
            'valid' => $allAvailable,
            'items' => $validatedItems,
            'subtotal' => $pricing['subtotal'],
            'discount' => $pricing['discount'],
            'delivery_fee' => $pricing['delivery_fee'],
            'total' => $pricing['grand_total'],
            'estimated_delivery' => "{$estMin} minutes",
            'payment_methods' => [
                'prepaid' => $paymentMethods['prepaid'],
                'cod' => $paymentMethods['cod'],
                'cod_disabled_reasons' => $paymentMethods['cod_disabled_reasons'],
                'gateways' => $paymentMethods['gateways'],
            ],
        ];
    }
}
