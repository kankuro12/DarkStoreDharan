<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Checkout\CheckoutService;
use App\Services\Inventory\StockReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentCallbackController extends Controller
{
    public function __construct(
        protected CheckoutService $checkoutService,
        protected StockReservationService $reservationService
    ) {}

    /**
     * Server-side webhook / callback verification for eSewa / Khalti (§18, §38.2).
     */
    public function handleCallback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_number' => 'required|string|exists:orders,order_number',
            'transaction_id' => 'required|string|max:100',
            'status' => 'required|string|in:SUCCESS,FAILED',
            'amount' => 'required|numeric',
            'gateway' => 'required|string|in:esewa,khalti,fonepay',
        ]);

        $order = Order::where('order_number', $validated['order_number'])->firstOrFail();

        // Check if already processed
        if ($order->payment_status === PaymentStatus::Paid) {
            return response()->json([
                'success' => true,
                'message' => 'Payment already verified.',
                'order_status' => $order->order_status->value,
            ]);
        }

        // Verify amount matches authoritative order grand_total (§38.4)
        if (abs((float) $order->grand_total - (float) $validated['amount']) > 0.01) {
            Log::warning("Payment amount mismatch for order {$order->order_number}: Expected {$order->grand_total}, got {$validated['amount']}");

            return response()->json([
                'success' => false,
                'message' => 'Payment amount does not match order grand total.',
            ], 422);
        }

        if ($validated['status'] === 'SUCCESS') {
            $this->checkoutService->confirmPrepaidOrder(
                $order,
                $validated['transaction_id'],
                $request->all()
            );

            return response()->json([
                'success' => true,
                'message' => 'Payment verified successfully. Order confirmed.',
                'order_number' => $order->order_number,
                'order_status' => $order->order_status->value,
                'payment_status' => $order->payment_status->value,
            ]);
        }

        // Handle Failed Payment (§19)
        $order->payment_status = PaymentStatus::Failed;
        $order->save();

        $order->statusLogs()->create([
            'status_type' => 'payment',
            'from_status' => PaymentStatus::Pending->value,
            'to_status' => PaymentStatus::Failed->value,
            'reason' => 'Payment failed via '.$validated['gateway'].'. Transaction ID: '.$validated['transaction_id'],
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Payment failed. Order remains pending until retry or expiration.',
            'order_status' => $order->order_status->value,
            'payment_status' => $order->payment_status->value,
        ]);
    }
}
