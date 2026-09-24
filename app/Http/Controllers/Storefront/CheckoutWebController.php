<?php

namespace App\Http\Controllers\Storefront;

use App\Exceptions\CartValidationException;
use App\Exceptions\OutOfStockException;
use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\ProductVariant;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutService;
use App\Services\Payment\PaymentMethodService;
use App\Services\Pricing\PricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutWebController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected PricingService $pricingService,
        protected PaymentMethodService $paymentMethodService,
        protected CheckoutService $checkoutService
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $cityId = $request->query('city_id') ?? $this->cartService->getSelectedCityId();
        if (! $cityId && $request->has('preview')) {
            $cityId = City::active()->first()?->id;
        }

        if (! $cityId) {
            return redirect()->route('storefront.home')->with('info', 'Please select your delivery city before proceeding to checkout.');
        }

        $city = City::with(['deliveryZones' => fn ($q) => $q->active()])->findOrFail($cityId);
        $cart = $this->cartService->getDetailedCart($cityId);

        if (empty($cart['items']) && ! $request->has('preview')) {
            return redirect()->route('storefront.home')->with('info', 'Your cart is empty. Please add items before checking out.');
        }

        if (empty($cart['items']) && $request->has('preview')) {
            $var = ProductVariant::with('product')->first();
            $cart = [
                'city_id' => $cityId,
                'items' => [
                    [
                        'variant_id' => $var?->id ?? 1,
                        'product_name' => ($var?->product?->name ?? 'Coca-Cola 500ml').' - '.($var?->name ?? '500ml'),
                        'unit_price' => $var?->price ?? 85.00,
                        'quantity' => 2,
                        'total' => ($var?->price ?? 85.00) * 2,
                    ],
                ],
                'item_count' => 2,
                'subtotal' => ($var?->price ?? 85.00) * 2,
                'discount' => 0,
                'delivery_fee' => 40.00,
                'grand_total' => (($var?->price ?? 85.00) * 2) + 40.00,
            ];
        }

        $rawItems = array_values($this->cartService->getItems());
        if (empty($rawItems) && $request->has('preview')) {
            $rawItems = [['variant_id' => $var?->id ?? 1, 'quantity' => 2]];
        }

        $paymentMethods = $this->paymentMethodService->resolveAvailableMethods(
            $cityId,
            $cart['grand_total'],
            $rawItems,
            auth()->user()
        );

        return view('storefront.checkout', [
            'city' => $city,
            'cart' => $cart,
            'zones' => $city->deliveryZones,
            'paymentMethods' => $paymentMethods,
        ]);
    }

    public function process(Request $request): RedirectResponse
    {
        $cityId = (int) $this->cartService->getSelectedCityId();

        $validated = $request->validate([
            'full_name' => 'required|string|max:100',
            'phone' => 'required|string|min:10|max:15',
            'delivery_zone_id' => 'nullable|integer|exists:delivery_zones,id',
            'area' => 'required|string|max:100',
            'street' => 'required|string|max:150',
            'landmark' => 'nullable|string|max:150',
            'delivery_notes' => 'nullable|string|max:500',
            'payment_method' => 'required|string|in:cod,esewa,khalti,fonepay',
            'coupon_code' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:500',
        ]);

        $orderPayload = [
            'city_id' => $cityId,
            'items' => array_values($this->cartService->getItems()),
            'payment_method' => $validated['payment_method'],
            'coupon_code' => $validated['coupon_code'] ?? $this->cartService->getAppliedCoupon(),
            'notes' => $validated['notes'] ?? null,
            'address' => [
                'full_name' => $validated['full_name'],
                'phone' => $validated['phone'],
                'delivery_zone_id' => $validated['delivery_zone_id'] ?? null,
                'area' => $validated['area'],
                'street' => $validated['street'],
                'landmark' => $validated['landmark'] ?? null,
                'delivery_notes' => $validated['delivery_notes'] ?? null,
            ],
        ];

        try {
            $order = $this->checkoutService->placeOrder($orderPayload, auth()->user());

            return redirect()->route('storefront.order.tracking', ['orderNumber' => $order->order_number])
                ->with('success', "Order #{$order->order_number} confirmed! Your dark store order is being prepared.");
        } catch (OutOfStockException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (CartValidationException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
}
