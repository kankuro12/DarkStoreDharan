<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Inventory\StockReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        protected StockReservationService $reservationService
    ) {}

    /**
     * Get order details.
     */
    public function show(Order $order): JsonResponse
    {
        $order->load(['city', 'warehouse', 'address', 'items', 'deliveryAgent']);

        return response()->json([
            'success' => true,
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'city' => $order->city?->name,
                'warehouse' => $order->warehouse?->name,
                'subtotal' => (float) $order->subtotal,
                'discount' => (float) $order->discount,
                'delivery_fee' => (float) $order->delivery_fee,
                'grand_total' => (float) $order->grand_total,
                'payment_method' => $order->payment_method->value,
                'order_status' => $order->order_status->value,
                'payment_status' => $order->payment_status->value,
                'delivery_status' => $order->delivery_status->value,
                'placed_at' => $order->placed_at?->toIso8601String(),
                'address' => $order->address ? [
                    'full_name' => $order->address->full_name,
                    'phone' => $order->address->phone,
                    'formatted' => $order->address->formatted_address,
                ] : null,
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'total' => (float) $item->total,
                ]),
                'rider' => $order->deliveryAgent ? [
                    'name' => $order->deliveryAgent->name,
                    'phone' => $order->deliveryAgent->phone,
                ] : null,
            ],
        ]);
    }

    /**
     * Live Tracking endpoint (§28, §29).
     */
    public function tracking(Order $order): JsonResponse
    {
        $order->load(['deliveryAgent', 'statusLogs', 'warehouse', 'city']);

        // Determine progress step index (0 to 4)
        $steps = [
            'confirmed' => $order->confirmed_at !== null || $order->order_status !== OrderStatus::Pending,
            'packed' => $order->packed_at !== null || in_array($order->order_status, [OrderStatus::Packed, OrderStatus::ReadyForDispatch, OrderStatus::Dispatched, OrderStatus::Delivered]),
            'dispatched' => $order->dispatched_at !== null || in_array($order->order_status, [OrderStatus::Dispatched, OrderStatus::Delivered]),
            'delivered' => $order->delivered_at !== null || $order->order_status === OrderStatus::Delivered,
        ];

        return response()->json([
            'success' => true,
            'tracking' => [
                'order_number' => $order->order_number,
                'order_status' => $order->order_status->value,
                'order_status_label' => $order->order_status->label(),
                'delivery_status' => $order->delivery_status->value,
                'delivery_status_label' => $order->delivery_status->label(),
                'steps' => $steps,
                'estimated_minutes' => $order->city?->estimated_delivery_minutes ?? 45,
                'warehouse' => $order->warehouse?->name,
                'rider' => $order->deliveryAgent ? [
                    'name' => $order->deliveryAgent->name,
                    'phone' => $order->deliveryAgent->phone,
                    'current_latitude' => $order->deliveryAgent->current_latitude,
                    'current_longitude' => $order->deliveryAgent->current_longitude,
                ] : null,
                'timeline' => $order->statusLogs->map(fn ($log) => [
                    'type' => $log->status_type,
                    'status' => $log->to_status,
                    'reason' => $log->reason,
                    'timestamp' => $log->created_at->toIso8601String(),
                ]),
            ],
        ]);
    }

    /**
     * Cancel an order.
     */
    public function cancel(Order $order, Request $request): JsonResponse
    {
        if (in_array($order->order_status, [OrderStatus::Dispatched, OrderStatus::Delivered, OrderStatus::Cancelled])) {
            return response()->json([
                'success' => false,
                'message' => 'This order can no longer be cancelled as it is already '.$order->order_status->value,
            ], 422);
        }

        $reason = $request->input('reason', 'Cancelled by customer');
        $order->transitionOrderStatus(OrderStatus::Cancelled, $reason, auth()->id());

        // Release inventory reservation if still held
        $this->reservationService->release($order);

        return response()->json([
            'success' => true,
            'message' => 'Order cancelled successfully and stock reservation released.',
            'order_status' => $order->order_status->value,
        ]);
    }
}
