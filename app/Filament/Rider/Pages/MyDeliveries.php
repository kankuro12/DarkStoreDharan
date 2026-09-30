<?php

namespace App\Filament\Rider\Pages;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class MyDeliveries extends Page
{
    protected string $view = 'filament.rider.pages.my-deliveries';

    protected static ?string $navigationLabel = 'My Deliveries';

    protected static ?string $title = 'My Deliveries';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    /**
     * @return Collection<int, Order>
     */
    public function getOrdersProperty(): Collection
    {
        return Order::query()
            ->with(['items', 'address', 'city'])
            ->where('delivery_agent_id', auth('delivery')->id())
            ->whereNotIn('delivery_status', [DeliveryStatus::Delivered, DeliveryStatus::Returned])
            ->where('order_status', '!=', OrderStatus::Cancelled->value)
            ->orderByDesc('dispatched_at')
            ->get();
    }

    public function getStatsProperty(): array
    {
        $agentId = auth('delivery')->id();

        $deliveredToday = Order::query()
            ->where('delivery_agent_id', $agentId)
            ->where('delivery_status', DeliveryStatus::Delivered)
            ->whereDate('delivered_at', today())
            ->count();

        $cashToCollect = Order::query()
            ->where('delivery_agent_id', $agentId)
            ->where('payment_method', PaymentMethod::Cod)
            ->where('payment_status', '!=', PaymentStatus::Paid)
            ->where('delivery_status', '!=', DeliveryStatus::Delivered)
            ->where('order_status', '!=', OrderStatus::Cancelled->value)
            ->sum('grand_total');

        return [
            'open' => $this->orders->count(),
            'deliveredToday' => $deliveredToday,
            'cashToCollect' => (float) $cashToCollect,
        ];
    }

    public function markPickedUp(int $orderId): void
    {
        $this->transitionDelivery($orderId, DeliveryStatus::PickedUp, 'Rider picked up the bag from the dark store');
    }

    public function markOutForDelivery(int $orderId): void
    {
        $this->transitionDelivery($orderId, DeliveryStatus::OutForDelivery, 'Rider is on the way to the customer');
    }

    public function markDelivered(int $orderId): void
    {
        $order = $this->findOwnOrder($orderId);

        if (! $order) {
            return;
        }

        $this->transitionDelivery($orderId, DeliveryStatus::Delivered, 'Delivered to customer by rider');
        $order->transitionOrderStatus(OrderStatus::Delivered, 'Delivered to customer by rider');

        Notification::make()
            ->title("Order {$order->order_number} marked delivered.")
            ->success()
            ->send();
    }

    public function markFailed(int $orderId): void
    {
        $this->transitionDelivery($orderId, DeliveryStatus::Failed, 'Delivery attempt failed');
    }

    private function transitionDelivery(int $orderId, DeliveryStatus $status, string $reason): void
    {
        $order = $this->findOwnOrder($orderId);

        if (! $order) {
            Notification::make()->title('Order not found in your deliveries.')->danger()->send();

            return;
        }

        $from = $order->delivery_status;
        $order->delivery_status = $status;
        $order->save();

        $order->statusLogs()->create([
            'user_id' => null,
            'status_type' => 'delivery',
            'from_status' => $from?->value,
            'to_status' => $status->value,
            'reason' => $reason,
        ]);

        Notification::make()
            ->title("Order {$order->order_number}: {$status->label()}")
            ->success()
            ->send();
    }

    /**
     * Riders can only act on orders assigned to themselves.
     */
    private function findOwnOrder(int $orderId): ?Order
    {
        return Order::query()
            ->whereKey($orderId)
            ->where('delivery_agent_id', auth('delivery')->id())
            ->first();
    }
}
