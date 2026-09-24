<?php

namespace App\Services\Payment;

use App\Models\Address;
use App\Models\City;
use App\Models\ProductVariant;
use App\Models\User;

class PaymentMethodService
{
    public const MAX_COD_AMOUNT = 15000.00; // Rs 15,000 max COD limit (§12, §38.4)

    /**
     * Resolve allowed payment methods (§10, §11, §38.3).
     *
     * @param  array<array{variant_id: int, quantity: int}>  $items
     * @return array{
     *     prepaid: bool,
     *     cod: bool,
     *     cod_disabled_reasons: array<string>,
     *     gateways: array<string>
     * }
     */
    public function resolveAvailableMethods(
        int $cityId,
        float $grandTotal,
        array $items,
        ?User $user = null,
        ?Address $address = null
    ): array {
        $city = City::find($cityId);
        $zone = $address?->deliveryZone;

        $prepaidAllowed = true;
        $codAllowed = true;
        $codDisabledReasons = [];

        // 1. City check
        if (! $city || ! $city->cod_enabled) {
            $codAllowed = false;
            $codDisabledReasons[] = 'Cash on Delivery is currently disabled in '.($city?->name ?? 'your city');
        }

        if (! $city || ! $city->prepaid_enabled) {
            $prepaidAllowed = false;
        }

        // 2. Delivery Zone check
        if ($zone && ! $zone->cod_enabled) {
            $codAllowed = false;
            $codDisabledReasons[] = "Cash on Delivery is not available in {$zone->name}.";
        }

        // 3. Product check: any non-COD product in cart?
        $variantIds = array_column($items, 'variant_id');
        $variantsWithCodBlocked = ProductVariant::whereIn('id', $variantIds)
            ->where('cod_allowed', false)
            ->pluck('name')
            ->toArray();

        if (! empty($variantsWithCodBlocked)) {
            $codAllowed = false;
            $codDisabledReasons[] = 'One or more items in your cart (e.g. '.implode(', ', array_slice($variantsWithCodBlocked, 0, 2)).') require online prepaid payment.';
        }

        // 4. Maximum COD Amount check (§12, §38.4)
        if ($grandTotal > self::MAX_COD_AMOUNT) {
            $codAllowed = false;
            $codDisabledReasons[] = 'Orders above Rs '.number_format(self::MAX_COD_AMOUNT).' must be paid online via digital wallet.';
        }

        // 5. Customer eligibility check (§11, §31.3)
        if ($user && $user->cod_blocked) {
            $codAllowed = false;
            $codDisabledReasons[] = 'Cash on delivery is unavailable for your account due to repeated delivery refusals. Please pay online.';
        }

        $availableGateways = [];
        if ($codAllowed) {
            $availableGateways[] = 'cod';
        }
        if ($prepaidAllowed) {
            $availableGateways[] = 'esewa';
            $availableGateways[] = 'khalti';
            $availableGateways[] = 'fonepay';
        }

        return [
            'prepaid' => $prepaidAllowed,
            'cod' => $codAllowed,
            'cod_disabled_reasons' => $codDisabledReasons,
            'gateways' => $availableGateways,
        ];
    }
}
