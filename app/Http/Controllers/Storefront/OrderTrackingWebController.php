<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Services\Inventory\StockReservationService;
use App\Services\Returns\ReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderTrackingWebController extends Controller
{
    public function __construct(
        protected ReturnService $returnService,
        protected StockReservationService $stockReservationService
    ) {}

    public function show(string $orderNumber): View
    {
        $order = Order::with(['items', 'address', 'warehouse', 'city', 'deliveryAgent', 'statusLogs'])
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        $returnRequest = ReturnRequest::where('order_id', $order->id)->latest()->first();

        $steps = [
            [
                'title' => 'Order Confirmed',
                'description' => 'Received by Dark Store',
                'completed' => $order->confirmed_at !== null || $order->order_status !== OrderStatus::Pending,
                'time' => $order->confirmed_at ? $order->confirmed_at->format('h:i A') : null,
            ],
            [
                'title' => 'Picking & Packing',
                'description' => 'Bag sealed at warehouse',
                'completed' => in_array($order->order_status, [OrderStatus::Packed, OrderStatus::ReadyForDispatch, OrderStatus::Dispatched, OrderStatus::Delivered]),
                'time' => $order->packed_at ? $order->packed_at->format('h:i A') : null,
            ],
            [
                'title' => 'Out for Delivery',
                'description' => $order->deliveryAgent ? "With Rider {$order->deliveryAgent->name}" : 'Assigned to fast delivery rider',
                'completed' => in_array($order->order_status, [OrderStatus::Dispatched, OrderStatus::Delivered]),
                'time' => $order->dispatched_at ? $order->dispatched_at->format('h:i A') : null,
            ],
            [
                'title' => 'Delivered',
                'description' => 'Delivered to your doorstep',
                'completed' => $order->order_status === OrderStatus::Delivered,
                'time' => $order->delivered_at ? $order->delivered_at->format('h:i A') : null,
            ],
        ];

        return view('storefront.tracking', [
            'order' => $order,
            'steps' => $steps,
            'returnRequest' => $returnRequest,
            'canCancel' => $order->isCancellable(),
        ]);
    }

    /**
     * Self-service cancellation, mirroring Amazon-style "cancel before it ships": available
     * any time before the store has packed the order for dispatch.
     */
    public function cancel(string $orderNumber, Request $request): RedirectResponse
    {
        $order = Order::where('order_number', $orderNumber)->firstOrFail();

        if (! $order->isCancellable()) {
            return back()->with('error', 'This order can no longer be cancelled as it is already '.$order->order_status->label().'.');
        }

        $order->transitionOrderStatus(OrderStatus::Cancelled, 'Cancelled by customer', auth()->id());
        $this->stockReservationService->release($order);

        return back()->with('success', "Order #{$order->order_number} has been cancelled.");
    }

    public function requestReturn(string $orderNumber, Request $request): RedirectResponse
    {
        $order = Order::where('order_number', $orderNumber)->firstOrFail();

        if ($order->order_status !== OrderStatus::Delivered) {
            return back()->with('error', 'Returns can only be requested for delivered orders.');
        }

        $validated = $request->validate([
            'reason_code' => 'required|string|in:wrong_item,damaged,expired,quality_issue,changed_mind',
            'reason_details' => 'nullable|string|max:500',
        ]);

        $this->returnService->requestReturn(
            $order,
            $validated['reason_code'],
            $validated['reason_details'],
            auth()->id()
        );

        return back()->with('success', 'Return request submitted. Our support team will review it within 30 minutes.');
    }
}
