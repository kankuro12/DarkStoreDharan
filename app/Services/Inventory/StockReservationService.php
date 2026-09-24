<?php

namespace App\Services\Inventory;

use App\Exceptions\OutOfStockException;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\StockReservation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockReservationService
{
    /**
     * Atomically reserve inventory items with row locking (§21, §38.2).
     *
     * @param  array<array{variant_id: int, quantity: int}>  $items
     *
     * @throws OutOfStockException
     */
    public function reserve(
        int $warehouseId,
        array $items,
        ?int $orderId = null,
        ?string $sessionId = null,
        int $ttlMinutes = 20
    ): Collection {
        return DB::transaction(function () use ($warehouseId, $items, $orderId, $sessionId, $ttlMinutes) {
            // Sort items by variant_id to avoid potential DB deadlocks during concurrent locks
            usort($items, fn ($a, $b) => $a['variant_id'] <=> $b['variant_id']);

            $reservations = collect();
            $unavailable = [];

            foreach ($items as $item) {
                $variantId = (int) $item['variant_id'];
                $requestedQty = (int) $item['quantity'];

                /** @var Inventory|null $inventory */
                $inventory = Inventory::where('warehouse_id', $warehouseId)
                    ->where('product_variant_id', $variantId)
                    ->lockForUpdate()
                    ->first();

                if (! $inventory || $inventory->available < $requestedQty) {
                    $unavailable[] = [
                        'variant_id' => $variantId,
                        'requested' => $requestedQty,
                        'available' => $inventory ? $inventory->available : 0,
                    ];
                }
            }

            if (! empty($unavailable)) {
                throw new OutOfStockException(
                    'Unable to reserve stock. Some items are no longer available in the required quantity.',
                    $unavailable
                );
            }

            // All items available; apply reservation
            $expiresAt = now()->addMinutes($ttlMinutes);

            foreach ($items as $item) {
                $variantId = (int) $item['variant_id'];
                $requestedQty = (int) $item['quantity'];

                $inventory = Inventory::where('warehouse_id', $warehouseId)
                    ->where('product_variant_id', $variantId)
                    ->first();

                $inventory->increment('reserved_quantity', $requestedQty);

                $reservation = StockReservation::create([
                    'order_id' => $orderId,
                    'session_id' => $sessionId,
                    'product_variant_id' => $variantId,
                    'warehouse_id' => $warehouseId,
                    'quantity' => $requestedQty,
                    'status' => 'reserved',
                    'expires_at' => $expiresAt,
                ]);

                $reservations->push($reservation);
            }

            return $reservations;
        });
    }

    /**
     * Confirm reservations permanently on order confirmation/payment (§18).
     * Deducts permanent quantity and releases the reserved hold.
     */
    public function confirm(Order|int $order): void
    {
        $orderId = $order instanceof Order ? $order->id : $order;

        DB::transaction(function () use ($orderId) {
            $reservations = StockReservation::where('order_id', $orderId)
                ->where('status', 'reserved')
                ->lockForUpdate()
                ->get();

            foreach ($reservations as $reservation) {
                $inventory = Inventory::where('warehouse_id', $reservation->warehouse_id)
                    ->where('product_variant_id', $reservation->product_variant_id)
                    ->lockForUpdate()
                    ->first();

                if ($inventory) {
                    // Decrement both total quantity and reserved hold
                    $inventory->quantity = max(0, $inventory->quantity - $reservation->quantity);
                    $inventory->reserved_quantity = max(0, $inventory->reserved_quantity - $reservation->quantity);
                    $inventory->save();
                }

                $reservation->update(['status' => 'confirmed']);
            }
        });
    }

    /**
     * Release reservations back to available stock if payment fails or order is cancelled (§19).
     */
    public function release(Order|int $order): void
    {
        $orderId = $order instanceof Order ? $order->id : $order;

        DB::transaction(function () use ($orderId) {
            $reservations = StockReservation::where('order_id', $orderId)
                ->where('status', 'reserved')
                ->lockForUpdate()
                ->get();

            foreach ($reservations as $reservation) {
                $inventory = Inventory::where('warehouse_id', $reservation->warehouse_id)
                    ->where('product_variant_id', $reservation->product_variant_id)
                    ->lockForUpdate()
                    ->first();

                if ($inventory) {
                    $inventory->reserved_quantity = max(0, $inventory->reserved_quantity - $reservation->quantity);
                    $inventory->save();
                }

                $reservation->update(['status' => 'released']);
            }
        });
    }

    /**
     * Release all expired reservations in the system (§19, §38.6).
     */
    public function releaseExpired(): int
    {
        return DB::transaction(function () {
            $expiredReservations = StockReservation::where('status', 'reserved')
                ->where('expires_at', '<=', now())
                ->lockForUpdate()
                ->get();

            $count = 0;

            foreach ($expiredReservations as $reservation) {
                $inventory = Inventory::where('warehouse_id', $reservation->warehouse_id)
                    ->where('product_variant_id', $reservation->product_variant_id)
                    ->lockForUpdate()
                    ->first();

                if ($inventory) {
                    $inventory->reserved_quantity = max(0, $inventory->reserved_quantity - $reservation->quantity);
                    $inventory->save();
                }

                $reservation->update(['status' => 'expired']);
                $count++;
            }

            return $count;
        });
    }
}
