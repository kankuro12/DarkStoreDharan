<?php

namespace App\Services\Delivery;

use App\Models\City;
use App\Models\FreeDeliveryRule;

class FreeDeliveryMatrixService
{
    /**
     * Decide whether an order qualifies for free delivery: either the city's flat subtotal
     * threshold, or any active product/category/subtotal rule in the free delivery matrix.
     *
     * @param  array<int, array{product_id: int, category_id: ?int, quantity: int}>  $cartItems
     * @return array{applied: bool, reason: ?string}
     */
    public function evaluate(int $cityId, array $cartItems, float $subtotal): array
    {
        $city = City::find($cityId);
        $flatThreshold = (float) ($city?->free_delivery_minimum ?? 0);

        if ($flatThreshold > 0 && $subtotal >= $flatThreshold) {
            return ['applied' => true, 'reason' => 'Order above Rs '.number_format($flatThreshold)];
        }

        $rules = FreeDeliveryRule::active()
            ->where(function ($query) use ($cityId) {
                $query->whereNull('city_id')->orWhere('city_id', $cityId);
            })
            ->get();

        foreach ($rules as $rule) {
            if ($rule->matches($cityId, $cartItems, $subtotal)) {
                return ['applied' => true, 'reason' => $rule->name];
            }
        }

        return ['applied' => false, 'reason' => null];
    }
}
