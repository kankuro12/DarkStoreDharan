<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\CartValidationException;
use App\Exceptions\OutOfStockException;
use App\Http\Controllers\Controller;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected CheckoutService $checkoutService
    ) {}

    /**
     * Checkout validation endpoint (§30 API specification).
     */
    public function validateCheckout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city_id' => 'required|integer|exists:cities,id',
            'address_id' => 'nullable|integer|exists:addresses,id',
            'items' => 'nullable|array',
            'items.*.variant_id' => 'required_with:items|integer|exists:product_variants,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
        ]);

        $result = $this->cartService->validateCheckout(
            cityId: (int) $validated['city_id'],
            addressId: isset($validated['address_id']) ? (int) $validated['address_id'] : null,
            overrideItems: $validated['items'] ?? null
        );

        return response()->json($result);
    }

    /**
     * Order placement endpoint (§16, §18, §20, §38.2).
     */
    public function placeOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city_id' => 'required|integer|exists:cities,id',
            'address_id' => 'nullable|integer|exists:addresses,id',
            'address' => 'required_without:address_id|array',
            'address.full_name' => 'required_with:address|string|max:100',
            'address.phone' => 'required_with:address|string|max:20',
            'address.area' => 'required_with:address|string|max:100',
            'address.street' => 'required_with:address|string|max:150',
            'address.landmark' => 'nullable|string|max:150',
            'address.delivery_zone_id' => 'nullable|integer|exists:delivery_zones,id',
            'address.delivery_notes' => 'nullable|string|max:500',
            'items' => 'nullable|array',
            'items.*.variant_id' => 'required_with:items|integer|exists:product_variants,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'payment_method' => 'required|string|in:cod,esewa,khalti,fonepay',
            'coupon_code' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:500',
        ]);

        // If items are not passed in payload, pull from session cart
        if (empty($validated['items'])) {
            $sessionItems = array_values($this->cartService->getItems());
            if (empty($sessionItems)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your cart is empty. Please add items before checking out.',
                ], 422);
            }
            $validated['items'] = $sessionItems;
        }

        if (empty($validated['coupon_code'])) {
            $validated['coupon_code'] = $this->cartService->getAppliedCoupon();
        }

        try {
            $order = $this->checkoutService->placeOrder($validated, $request->user());

            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully.',
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'grand_total' => (float) $order->grand_total,
                    'order_status' => $order->order_status->value,
                    'payment_status' => $order->payment_status->value,
                    'payment_method' => $order->payment_method->value,
                    'delivery_status' => $order->delivery_status->value,
                    'warehouse' => $order->warehouse?->name,
                    'city' => $order->city?->name,
                    'placed_at' => $order->placed_at?->toIso8601String(),
                ],
            ], 201);
        } catch (OutOfStockException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'unavailable_items' => $e->unavailableItems,
            ], 422);
        } catch (CartValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
