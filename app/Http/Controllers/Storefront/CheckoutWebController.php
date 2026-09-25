<?php

namespace App\Http\Controllers\Storefront;

use App\Exceptions\CartValidationException;
use App\Exceptions\OutOfStockException;
use App\Http\Controllers\Controller;
use App\Models\Address;
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

        $savedAddresses = $request->user()?->addresses()->where('city_id', $cityId)->with('deliveryZone')->latest()->get() ?? collect();

        return view('storefront.checkout', [
            'city' => $city,
            'cart' => $cart,
            'zones' => $city->deliveryZones,
            'paymentMethods' => $paymentMethods,
            'savedAddresses' => $savedAddresses,
        ]);
    }

    public function process(Request $request): RedirectResponse
    {
        $cityId = (int) $this->cartService->getSelectedCityId();
        $usingSavedAddress = $request->filled('address_id');

        $validated = $request->validate([
            'address_id' => 'nullable|integer|exists:addresses,id',
            'full_name' => $usingSavedAddress ? 'nullable|string|max:100' : 'required|string|max:100',
            'phone' => $usingSavedAddress ? 'nullable|string|min:10|max:15' : 'required|string|min:10|max:15',
            'delivery_zone_id' => 'nullable|integer|exists:delivery_zones,id',
            'area' => $usingSavedAddress ? 'nullable|string|max:100' : 'required|string|max:100',
            'street' => $usingSavedAddress ? 'nullable|string|max:150' : 'required|string|max:150',
            'landmark' => 'nullable|string|max:150',
            'delivery_notes' => 'nullable|string|max:500',
            'payment_method' => 'required|string|in:cod,esewa,khalti,fonepay',
            'coupon_code' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($usingSavedAddress) {
            $address = Address::findOrFail($validated['address_id']);
            if ($address->user_id !== $request->user()?->id) {
                abort(403);
            }

            $orderPayload = [
                'city_id' => $cityId,
                'address_id' => $address->id,
            ];
        } else {
            $orderPayload = [
                'city_id' => $cityId,
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
        }

        $orderPayload['items'] = array_values($this->cartService->getItems());
        $orderPayload['payment_method'] = $validated['payment_method'];
        $orderPayload['coupon_code'] = $validated['coupon_code'] ?? $this->cartService->getAppliedCoupon();
        $orderPayload['notes'] = $validated['notes'] ?? null;

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
