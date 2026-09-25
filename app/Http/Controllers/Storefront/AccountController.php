<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\City;
use App\Models\Order;
use App\Services\Cart\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function __construct(
        protected CartService $cartService
    ) {}

    /**
     * Order history for the authenticated customer, newest first.
     */
    public function orders(Request $request): View
    {
        $orders = $request->user()->orders()
            ->with(['items', 'city'])
            ->latest('placed_at')
            ->paginate(10);

        return view('storefront.account.orders', [
            'orders' => $orders,
        ]);
    }

    /**
     * Amazon-style one-click reorder: add every item from a past order back
     * into the active cart for the same city, skipping anything now unavailable.
     */
    public function reorder(Request $request, Order $order): RedirectResponse
    {
        if ($order->user_id !== $request->user()->id) {
            abort(403);
        }

        $added = 0;
        $skipped = [];

        foreach ($order->items as $item) {
            $result = $this->cartService->addItem((int) $item->variant_id, (int) $item->quantity, (int) $order->city_id);

            if ($result['success']) {
                $added++;
            } else {
                $skipped[] = $item->product_name;
            }
        }

        if ($added === 0) {
            return back()->with('error', 'None of the items from this order are currently available to reorder.');
        }

        $message = "{$added} item(s) from order #{$order->order_number} added to your basket.";
        if (! empty($skipped)) {
            $message .= ' Unavailable: '.implode(', ', $skipped).'.';
        }

        return redirect()->route('storefront.home', ['city_id' => $order->city_id])->with('success', $message);
    }

    /**
     * Saved delivery addresses, Amazon-style address book.
     */
    public function addresses(Request $request): View
    {
        $addresses = $request->user()->addresses()->with('city', 'deliveryZone')->latest()->get();
        $cities = City::with(['deliveryZones' => fn ($q) => $q->active()])->active()->get();

        return view('storefront.account.addresses', [
            'addresses' => $addresses,
            'cities' => $cities,
        ]);
    }

    public function storeAddress(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'city_id' => 'required|integer|exists:cities,id',
            'delivery_zone_id' => 'nullable|integer|exists:delivery_zones,id',
            'full_name' => 'required|string|max:100',
            'phone' => 'required|string|min:10|max:15',
            'area' => 'required|string|max:100',
            'street' => 'required|string|max:150',
            'landmark' => 'nullable|string|max:150',
            'delivery_notes' => 'nullable|string|max:500',
        ]);

        $request->user()->addresses()->create($validated);

        return back()->with('success', 'Address saved to your account.');
    }

    public function updateAddress(Request $request, Address $address): RedirectResponse
    {
        if ($address->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'city_id' => 'required|integer|exists:cities,id',
            'delivery_zone_id' => 'nullable|integer|exists:delivery_zones,id',
            'full_name' => 'required|string|max:100',
            'phone' => 'required|string|min:10|max:15',
            'area' => 'required|string|max:100',
            'street' => 'required|string|max:150',
            'landmark' => 'nullable|string|max:150',
            'delivery_notes' => 'nullable|string|max:500',
        ]);

        $address->update($validated);

        return back()->with('success', 'Address updated.');
    }

    public function destroyAddress(Request $request, Address $address): RedirectResponse
    {
        if ($address->user_id !== $request->user()->id) {
            abort(403);
        }

        $address->delete();

        return back()->with('info', 'Address removed from your account.');
    }
}
