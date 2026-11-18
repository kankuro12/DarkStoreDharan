<?php

namespace App\Services\Delivery;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\DeliveryAgent;
use App\Models\Order;
use App\Models\OrderOfferDecline;
use Illuminate\Support\Collection;

/** DoorDash-style offer board: riders see the pay up front and the first to accept wins. */
class DeliveryOfferService
{
    public const MAX_OPEN_DELIVERIES = 2;

    public const OPEN_ORDER_STATUSES = [OrderStatus::Processing, OrderStatus::Packed, OrderStatus::ReadyForDispatch];

    /** @return Collection<int, Order> */
    public function openOffersFor(DeliveryAgent $rider): Collection
    {
        if (! DeliveryAgent::available()->whereKey($rider->id)->exists()) {
            return collect();
        }

        $declined = OrderOfferDecline::where('delivery_agent_id', $rider->id)->pluck('declined_pay', 'order_id');

        return $this->openOffersQuery()
            ->where('city_id', $rider->city_id)
            ->with(['address.deliveryZone', 'warehouse', 'items', 'city'])
            ->oldest('offer_opened_at')
            ->get()
            ->reject(fn (Order $order) => $declined->has($order->id)
                && (float) $declined[$order->id] >= $order->currentOfferPay())
            ->values();
    }

    public function accept(Order $order, DeliveryAgent $rider): bool
    {
        $eligible = DeliveryAgent::available()->whereKey($rider->id)->exists()
            && $order->city_id === $rider->city_id
            && $rider->openDeliveriesCount() < self::MAX_OPEN_DELIVERIES;

        if (! $eligible) {
            return false;
        }

        $pay = $order->currentOfferPay();

        // One conditional UPDATE: if another rider (or staff) got there first, nothing matches.
        $won = $this->openOffersQuery()->whereKey($order->id)->update([
            'delivery_agent_id' => $rider->id,
            'delivery_status' => DeliveryStatus::Assigned->value,
            'rider_pay' => $pay,
        ]) === 1;

        if ($won) {
            AuditLog::record('delivery_offer_accepted', $order->fresh(), null, ['delivery_agent_id' => $rider->id, 'rider_pay' => $pay], 'Rider accepted delivery offer');
        }

        return $won;
    }

    public function decline(Order $order, DeliveryAgent $rider): void
    {
        OrderOfferDecline::updateOrCreate(
            ['order_id' => $order->id, 'delivery_agent_id' => $rider->id],
            ['declined_pay' => $order->currentOfferPay() ?? 0],
        );
    }

    public function assignManually(Order $order, DeliveryAgent $rider, ?int $userId): void
    {
        $order->forceFill([
            'delivery_agent_id' => $rider->id,
            'delivery_status' => DeliveryStatus::Assigned,
            'rider_pay' => $order->currentOfferPay() ?? (float) ($order->city?->rider_base_pay ?? 0),
        ])->save();

        AuditLog::record('delivery_manually_assigned', $order, null, ['delivery_agent_id' => $rider->id, 'rider_pay' => $order->rider_pay], 'Staff assigned rider', $userId);
    }

    public static function distanceKm(Order $order): ?float
    {
        $coords = [$order->warehouse?->latitude, $order->warehouse?->longitude, $order->address?->latitude, $order->address?->longitude];

        if (in_array(null, $coords, true) || in_array('', $coords, true)) {
            return null;
        }

        [$lat1, $lon1, $lat2, $lon2] = array_map(fn ($v) => deg2rad((float) $v), $coords);
        $a = sin(($lat2 - $lat1) / 2) ** 2 + cos($lat1) * cos($lat2) * sin(($lon2 - $lon1) / 2) ** 2;

        return round(6371 * 2 * asin(sqrt($a)), 1);
    }

    private function openOffersQuery()
    {
        return Order::query()
            ->whereNull('delivery_agent_id')
            ->whereNotNull('offer_opened_at')
            ->whereIn('order_status', array_map(fn ($s) => $s->value, self::OPEN_ORDER_STATUSES));
    }
}
