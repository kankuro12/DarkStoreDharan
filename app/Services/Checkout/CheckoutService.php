<?php

namespace App\Services\Checkout;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\CartValidationException;
use App\Models\Address;
use App\Models\AuditLog;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Fulfillment\WarehouseSelector;
use App\Services\Inventory\StockReservationService;
use App\Services\Payment\PaymentMethodService;
use App\Services\Pricing\PricingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutService
{
    public function __construct(
        protected WarehouseSelector $warehouseSelector,
        protected PricingService $pricingService,
        protected PaymentMethodService $paymentMethodService,
        protected StockReservationService $stockReservationService,
        protected CartService $cartService
    ) {}

    /**
     * Complete order placement with atomic locking, verification, and snapshots (§16, §18, §20, §38.2).
     *
     * @param array{
     *     city_id: int,
     *     address_id?: int,
     *     address?: array,
     *     items: array<array{variant_id: int, quantity: int}>,
     *     payment_method: string,
     *     coupon_code?: string,
     *     notes?: string
     * } $data
     *
     * @throws CartValidationException
     */
    public function placeOrder(array $data, ?User $user = null): Order
    {
        $cityId = (int) $data['city_id'];
        $items = $data['items'] ?? [];
        $paymentMethod = PaymentMethod::tryFrom($data['payment_method']) ?? PaymentMethod::Cod;
        $couponCode = $data['coupon_code'] ?? null;
        $notes = $data['notes'] ?? null;

        if (empty($items)) {
            throw new CartValidationException('Cannot place an order with an empty cart.');
        }

        $city = City::findOrFail($cityId);

        // 1. Resolve or create delivery address
        $address = null;
        if (! empty($data['address_id'])) {
            $address = Address::findOrFail($data['address_id']);
        } elseif (! empty($data['address'])) {
            $addrData = $data['address'];
            $address = Address::create([
                'user_id' => $user?->id,
                'city_id' => $cityId,
                'delivery_zone_id' => $addrData['delivery_zone_id'] ?? null,
                'full_name' => $addrData['full_name'],
                'phone' => $addrData['phone'],
                'area' => $addrData['area'],
                'street' => $addrData['street'],
                'landmark' => $addrData['landmark'] ?? null,
                'delivery_notes' => $addrData['delivery_notes'] ?? null,
            ]);
        }

        // 2. Select warehouse capable of 100% fulfillment (§15)
        $warehouse = $this->warehouseSelector->selectWarehouseForOrder($cityId, $items);

        // 3. Authoritative server-side pricing recalculation (§38.2)
        $zoneId = $address?->delivery_zone_id;
        $pricing = $this->pricingService->calculate($cityId, $items, $couponCode, $zoneId, $warehouse->id);

        // 4. Verify payment method eligibility (§10, §11, §38.4)
        $methodRules = $this->paymentMethodService->resolveAvailableMethods(
            $cityId,
            $pricing['grand_total'],
            $items,
            $user,
            $address
        );

        if ($paymentMethod === PaymentMethod::Cod && ! $methodRules['cod']) {
            $reason = implode(' ', $methodRules['cod_disabled_reasons']);
            throw new CartValidationException("Cash on Delivery is unavailable for this order: {$reason}");
        }

        // 5. Atomic Reservation and Order Creation (§21)
        return DB::transaction(function () use (
            $user,
            $city,
            $warehouse,
            $address,
            $pricing,
            $paymentMethod,
            $notes
        ) {
            // Reserve inventory with row locks
            $reservations = $this->stockReservationService->reserve(
                $warehouse->id,
                $pricing['items'],
                null,
                session()->getId(),
                25 // 25 min timeout
            );

            $orderNumber = 'ORD-'.date('ymd').'-'.strtoupper(Str::random(5));

            $isCod = $paymentMethod === PaymentMethod::Cod;

            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => $user?->id,
                'city_id' => $city->id,
                'warehouse_id' => $warehouse->id,
                'address_id' => $address?->id,
                'subtotal' => $pricing['subtotal'],
                'discount' => $pricing['discount'],
                'delivery_fee' => $pricing['delivery_fee'],
                'tax' => $pricing['tax'],
                'grand_total' => $pricing['grand_total'],
                'payment_method' => $paymentMethod,
                'order_status' => $isCod ? OrderStatus::Confirmed : OrderStatus::Pending,
                'payment_status' => $isCod ? PaymentStatus::Unpaid : PaymentStatus::Pending,
                'delivery_status' => DeliveryStatus::Pending,
                'placed_at' => now(),
                'confirmed_at' => $isCod ? now() : null,
                'notes' => $notes,
            ]);

            // Link reservations to order
            foreach ($reservations as $reservation) {
                $reservation->update(['order_id' => $order->id]);
            }

            // Create immutable OrderItem snapshots (§16, §38.2)
            foreach ($pricing['items'] as $calcItem) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $calcItem['product_id'],
                    'variant_id' => $calcItem['variant_id'],
                    'product_name' => $calcItem['product_name'],
                    'sku' => $calcItem['sku'],
                    'quantity' => $calcItem['quantity'],
                    'unit_price' => $calcItem['unit_price'],
                    'discount' => 0.00,
                    'tax' => 0.00,
                    'total' => $calcItem['total'],
                ]);
            }

            // If COD, confirm stock immediately (§20)
            if ($isCod) {
                $this->stockReservationService->confirm($order);
            }

            // Record initial lifecycle status log
            $order->statusLogs()->create([
                'user_id' => $user?->id,
                'status_type' => 'order',
                'from_status' => null,
                'to_status' => $order->order_status->value,
                'reason' => $isCod ? 'Order placed and confirmed with Cash on Delivery' : 'Order placed, awaiting digital payment confirmation',
            ]);

            // Record Coupon Usage
            if ($pricing['coupon']) {
                $pricing['coupon']->increment('used_count');
            }

            // Clear session cart
            $this->cartService->clear();

            // Append-only audit record (§31.7)
            AuditLog::record(
                'order_placed',
                $order,
                null,
                [
                    'order_number' => $order->order_number,
                    'grand_total' => $order->grand_total,
                    'warehouse' => $warehouse->name,
                    'city' => $city->name,
                ],
                'New order placed by customer',
                $user?->id
            );

            return $order;
        });
    }

    /**
     * Confirm prepaid payment via server-to-server gateway callback (§18, §38.2).
     */
    public function confirmPrepaidOrder(Order $order, string $transactionId, array $gatewayPayload = []): void
    {
        DB::transaction(function () use ($order, $transactionId, $gatewayPayload) {
            $order->payment_status = PaymentStatus::Paid;
            $order->order_status = OrderStatus::Confirmed;
            $order->confirmed_at = now();
            $order->save();

            // Create Payment record
            $order->payments()->create([
                'transaction_id' => $transactionId,
                'gateway' => $order->payment_method->value,
                'amount' => $order->grand_total,
                'status' => 'paid',
                'raw_response' => $gatewayPayload,
            ]);

            // Convert temporary stock reservation to permanent deduction
            $this->stockReservationService->confirm($order);

            // Log status transition
            $order->statusLogs()->create([
                'status_type' => 'payment',
                'from_status' => PaymentStatus::Pending->value,
                'to_status' => PaymentStatus::Paid->value,
                'reason' => 'Online payment verified with gateway. Transaction ID: '.$transactionId,
            ]);

            $order->statusLogs()->create([
                'status_type' => 'order',
                'from_status' => OrderStatus::Pending->value,
                'to_status' => OrderStatus::Confirmed->value,
                'reason' => 'Order auto-confirmed upon successful payment verification',
            ]);
        });
    }
}
