<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Cart\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        protected CartService $cartService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $cityId = $request->query('city_id', $this->cartService->getSelectedCityId());
        $cart = $this->cartService->getDetailedCart($cityId ? (int) $cityId : null);

        return response()->json([
            'success' => true,
            'cart' => $cart,
        ]);
    }

    public function addItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'variant_id' => 'required|integer|exists:product_variants,id',
            'quantity' => 'required|integer|min:1|max:50',
            'city_id' => 'required|integer|exists:cities,id',
        ]);

        $result = $this->cartService->addItem(
            (int) $validated['variant_id'],
            (int) $validated['quantity'],
            (int) $validated['city_id']
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function updateItem(int $variantId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:0|max:50',
        ]);

        $result = $this->cartService->updateQuantity($variantId, (int) $validated['quantity']);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function removeItem(int $variantId): JsonResponse
    {
        $result = $this->cartService->removeItem($variantId);

        return response()->json($result);
    }

    /**
     * City Switch Endpoint (§14, §38.5).
     * Revalidates cart items against new city and notifies customer of changes.
     */
    public function changeCity(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city_id' => 'required|integer|exists:cities,id',
        ]);

        $result = $this->cartService->changeCity((int) $validated['city_id']);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    public function applyCoupon(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50',
        ]);

        $this->cartService->applyCoupon($validated['code']);

        return response()->json([
            'success' => true,
            'message' => 'Coupon code applied.',
            'cart' => $this->cartService->getDetailedCart(),
        ]);
    }

    public function removeCoupon(): JsonResponse
    {
        $this->cartService->removeCoupon();

        return response()->json([
            'success' => true,
            'message' => 'Coupon removed.',
            'cart' => $this->cartService->getDetailedCart(),
        ]);
    }
}
