<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Sms\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendOrderTrackingSms implements ShouldQueue
{
    use Queueable;

    public function __construct(protected int $orderId) {}

    public function handle(SmsService $smsService): void
    {
        $order = Order::with('address')->find($this->orderId);

        if (! $order || ! $order->address?->phone) {
            return;
        }

        $trackingUrl = route('storefront.order.tracking', $order->order_number);

        $message = "Your order #{$order->order_number} is confirmed! Track live status: {$trackingUrl}";

        $sent = $smsService->send($order->address->phone, $message);

        if (! $sent) {
            Log::warning('Order tracking SMS could not be sent', ['order_id' => $order->id]);
        }
    }
}
