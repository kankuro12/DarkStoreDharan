<?php

namespace App\Services\Pricing;

use App\Models\City;
use App\Models\Coupon;
use App\Models\DeliveryZone;
use App\Models\ProductVariant;
use App\Services\Delivery\FreeDeliveryMatrixService;

class PricingService
{
    public function __construct(
        protected FreeDeliveryMatrixService $freeDeliveryMatrixService
    ) {}

    /**
     * Calculate all order financial totals server-side (§38.2, §38.4).
     *
     * @param  array<array{variant_id: int, quantity: int}>  $items
     * @return array{
     *     items: array,
     *     subtotal: float,
     *     discount: float,
     *     delivery_fee: float,
     *     tax: float,
     *     grand_total: float,
     *     coupon: ?Coupon,
     *     free_delivery_applied: bool
     * }
     */
    public function calculate(
        int $cityId,
        array $items,
        ?string $couponCode = null,
        ?int $zoneId = null,
        ?int $warehouseId = null
    ): array {
        $city = City::findOrFail($cityId);
        $zone = $zoneId ? DeliveryZone::find($zoneId) : null;

        $calculatedItems = [];
        $subtotal = 0.0;

        foreach ($items as $item) {
            $variant = ProductVariant::with('product')->findOrFail($item['variant_id']);
            $qty = max(1, (int) $item['quantity']);

            $priceInfo = $variant->getPriceForCity($cityId, $warehouseId);
            $unitPrice = (float) $priceInfo['price'];
            $lineTotal = round($unitPrice * $qty, 2);

            $subtotal += $lineTotal;

            $calculatedItems[] = [
                'variant_id' => $variant->id,
                'product_id' => $variant->product_id,
                'category_id' => $variant->product->category_id,
                'product_name' => $variant->product->name.' - '.$variant->name,
                'sku' => $variant->sku,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'regular_price' => (float) $priceInfo['regular_price'],
                'is_sale' => $priceInfo['is_sale'],
                'total' => $lineTotal,
                'cod_allowed' => $variant->cod_allowed,
            ];
        }

        $subtotal = round($subtotal, 2);

        // Calculate Coupon Discount
        $discount = 0.0;
        $appliedCoupon = null;

        if ($couponCode) {
            $coupon = Coupon::where('code', strtoupper(trim($couponCode)))->first();
            if ($coupon && $coupon->isValidFor($cityId, $subtotal)) {
                $discount = $coupon->calculateDiscount($subtotal);
                $appliedCoupon = $coupon;
            }
        }

        // Determine Delivery Fee: city/zone base fee, waived by the free delivery matrix
        // (a flat per-city subtotal threshold, or any active product/category rule).
        $baseDeliveryFee = $zone ? (float) $zone->delivery_fee : (float) $city->default_delivery_fee;

        $freeDelivery = $this->freeDeliveryMatrixService->evaluate($cityId, $calculatedItems, $subtotal);
        $freeDeliveryApplied = $freeDelivery['applied'];
        $deliveryFee = $freeDeliveryApplied ? 0.0 : $baseDeliveryFee;

        $tax = 0.0; // Grocery tax exempt or inclusive by default
        $grandTotal = max(0.0, round($subtotal - $discount + $deliveryFee + $tax, 2));

        return [
            'items' => $calculatedItems,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'delivery_fee' => $deliveryFee,
            'tax' => $tax,
            'grand_total' => $grandTotal,
            'coupon' => $appliedCoupon,
            'free_delivery_applied' => $freeDeliveryApplied,
            'free_delivery_reason' => $freeDelivery['reason'],
        ];
    }
}
