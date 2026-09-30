<?php

namespace App\Services\Inventory;

use App\Enums\InventoryMovementType;
use App\Exceptions\OutOfStockException;
use App\Models\AuditLog;
use App\Models\Inventory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class InventoryService
{
    public function __construct(protected InventoryLedgerService $ledger) {}

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
     * Every adjustment is also recorded in the immutable stock ledger.
     */
    public function adjustStock(
        int $warehouseId,
        int $variantId,
        int $quantityDelta,
        string $reason,
        ?int $userId = null
    ): Inventory {
        return DB::transaction(function () use ($warehouseId, $variantId, $quantityDelta, $reason, $userId) {
            $inventory = $this->lockOrCreateInventory($warehouseId, $variantId);

            $beforeState = [
                'quantity' => $inventory->quantity,
                'reserved_quantity' => $inventory->reserved_quantity,
                'available' => $inventory->available,
            ];

            $balanceBefore = (int) $inventory->quantity;
            $inventory->quantity = max(0, $balanceBefore + $quantityDelta);
            $inventory->save();

            $this->ledger->record(
                $inventory,
                $quantityDelta >= 0 ? InventoryMovementType::In : InventoryMovementType::Out,
                $quantityDelta,
                $reason,
                null,
                null,
                $userId,
                $balanceBefore,
            );

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

    /**
     * Move stock between two warehouses as a single paired ledger transfer.
     * Only unreserved stock can leave the source warehouse.
     */
    public function transfer(
        int $fromWarehouseId,
        int $toWarehouseId,
        int $variantId,
        int $quantity,
        string $reason,
        ?int $userId = null
    ): void {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Transfer quantity must be at least 1.');
        }

        if ($fromWarehouseId === $toWarehouseId) {
            throw new InvalidArgumentException('Source and destination warehouse must be different.');
        }

        DB::transaction(function () use ($fromWarehouseId, $toWarehouseId, $variantId, $quantity, $reason, $userId) {
            $source = Inventory::where('warehouse_id', $fromWarehouseId)
                ->where('product_variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            if (! $source || $source->available < $quantity) {
                throw new OutOfStockException(
                    'Not enough available stock to transfer.',
                    [['variant_id' => $variantId, 'requested' => $quantity, 'available' => $source?->available ?? 0]],
                );
            }

            $destination = $this->lockOrCreateInventory($toWarehouseId, $variantId);
            $code = 'TRF-'.strtoupper(Str::random(6));

            $sourceBefore = (int) $source->quantity;
            $source->quantity -= $quantity;
            $source->save();

            $destinationBefore = (int) $destination->quantity;
            $destination->quantity += $quantity;
            $destination->save();

            $this->ledger->record(
                $source,
                InventoryMovementType::TransferOut,
                -$quantity,
                $reason,
                null,
                $code,
                $userId,
                $sourceBefore,
            );

            $this->ledger->record(
                $destination,
                InventoryMovementType::TransferIn,
                $quantity,
                $reason,
                null,
                $code,
                $userId,
                $destinationBefore,
            );

            AuditLog::record(
                'stock_transferred',
                $source,
                ['quantity' => $sourceBefore],
                ['quantity' => $source->quantity, 'transfer_code' => $code, 'destination_warehouse_id' => $toWarehouseId],
                $reason,
                $userId
            );
        });
    }

    private function lockOrCreateInventory(int $warehouseId, int $variantId): Inventory
    {
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

        return $inventory;
    }
}
