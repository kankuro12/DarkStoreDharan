<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Cart\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CartWebController extends Controller
{
    public function __construct(
        protected CartService $cartService
    ) {}

    public function add(Request $request)
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

        if ($request->wantsJson()) {
            if ($result['success']) {
                $result['cart'] = $this->cartService->getDetailedCart((int) $validated['city_id']);
            }
            return response()->json($result, $result['success'] ? 200 : 422);
        }

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', 'Item added to your basket!');
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'variant_id' => 'required|integer',
            'quantity' => 'required|integer|min:0|max:50',
        ]);

        $result = $this->cartService->updateQuantity((int) $validated['variant_id'], (int) $validated['quantity']);

        if ($request->wantsJson()) {
            return response()->json($result, $result['success'] ? 200 : 422);
        }

        return back();
    }

    public function remove(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'variant_id' => 'required|integer',
        ]);

        $this->cartService->removeItem((int) $validated['variant_id']);

        return back()->with('info', 'Item removed from basket.');
    }

    /**
     * Switch city and display revalidation alerts (§14, §38.5).
     */
    public function changeCity(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'city_id' => 'required|integer|exists:cities,id',
        ]);

        $result = $this->cartService->changeCity((int) $validated['city_id']);

        if ($result['has_changes']) {
            $warnings = [];
            foreach ($result['unavailable_items'] as $un) {
                $warnings[] = "• '{$un['name']}' was updated because only {$un['available_in_new_city']} is available in {$result['city']->name}.";
            }
            foreach ($result['price_changed_items'] as $pc) {
                $warnings[] = "• '{$pc['name']}' price changed from Rs {$pc['old_price']} to Rs {$pc['new_price']}.";
            }

            return redirect()->route('storefront.home', ['city_id' => $validated['city_id']])
                ->with('cart_warning', implode('<br>', $warnings))
                ->with('info', $result['message']);
        }

        return redirect()->route('storefront.home', ['city_id' => $validated['city_id']])
            ->with('success', "Location switched to {$result['city']->name}. Showing available inventory.");
    }

    public function applyCoupon(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50',
        ]);

        $this->cartService->applyCoupon($validated['code']);

        return back()->with('success', 'Coupon code applied!');
    }

    public function removeCoupon(): RedirectResponse
    {
        $this->cartService->removeCoupon();

        return back()->with('info', 'Coupon code removed.');
    }
}
