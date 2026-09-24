<?php

namespace App\Services\Inventory;

use App\Models\AuditLog;
use App\Models\Inventory;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Check if a warehouse has enough stock for a variant.
     */
    public function checkAvailability(int $warehouseId, int $variantId, int $requiredQuantity): bool
    {
        $inventory = Inventory::where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $variantId)
            ->first();

        return $inventory && $inventory->available >= $requiredQuantity;
    }

    /**
     * Adjust warehouse inventory with row-locking and mandatory audit log (§21, §31.7, §38.2).
     */
    public function adjustStock(
        int $warehouseId,
        int $variantId,
        int $quantityDelta,
        string $reason,
        ?int $userId = null
    ): Inventory {
        return DB::transaction(function () use ($warehouseId, $variantId, $quantityDelta, $reason, $userId) {
            $inventory = Inventory::where('warehouse_id', $warehouseId)
                ->where('product_variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            if (! $inventory) {
                $inventory = Inventory::create([
                    'warehouse_id' => $warehouseId,
                    'product_variant_id' => $variantId,
                    'quantity' => 0,
                    'reserved_quantity' => 0,
                    'reorder_level' => 5,
                ]);
                $inventory = Inventory::where('id', $inventory->id)->lockForUpdate()->first();
            }

            $beforeState = [
                'quantity' => $inventory->quantity,
                'reserved_quantity' => $inventory->reserved_quantity,
                'available' => $inventory->available,
            ];

            $newQuantity = max(0, (int) $inventory->quantity + $quantityDelta);
            $inventory->quantity = $newQuantity;
            $inventory->save();

            $afterState = [
                'quantity' => $inventory->quantity,
                'reserved_quantity' => $inventory->reserved_quantity,
                'available' => $inventory->available,
            ];

            AuditLog::record(
                'stock_adjusted',
                $inventory,
                $beforeState,
                $afterState,
                $reason,
                $userId
            );

            return $inventory;
        });
    }
}
