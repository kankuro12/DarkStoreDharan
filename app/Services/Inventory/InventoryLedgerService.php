<?php

namespace App\Services\Inventory;

use App\Enums\InventoryMovementType;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use Illuminate\Database\Eloquent\Model;

class InventoryLedgerService
{
    /**
     * Append an immutable movement to the stock ledger.
     *
     * The first movement ever recorded for a (warehouse, variant) pair is
     * automatically preceded by an `opening` entry frozen at the quantity the
     * shelf had before this change, so every ledger starts from a known balance.
     * Callers pass the signed change and the quantity *after* the change is
     * already saved on the inventory row.
     */
    public function record(
        Inventory $inventory,
        InventoryMovementType $type,
        int $signedQuantity,
        ?string $reason = null,
        ?Model $reference = null,
        ?string $referenceCode = null,
        ?int $userId = null,
        ?int $balanceBefore = null,
    ): InventoryMovement {
        $hasHistory = InventoryMovement::query()
            ->where('warehouse_id', $inventory->warehouse_id)
            ->where('product_variant_id', $inventory->product_variant_id)
            ->exists();

        if (! $hasHistory) {
            $opening = $balanceBefore ?? ((int) $inventory->quantity - $signedQuantity);

            InventoryMovement::create([
                'warehouse_id' => $inventory->warehouse_id,
                'product_variant_id' => $inventory->product_variant_id,
                'type' => InventoryMovementType::Opening,
                'quantity' => $opening,
                'balance_after' => $opening,
                'reference_code' => 'OPENING',
                'reason' => 'Opening balance',
                'created_by' => $userId,
                'created_at' => now(),
            ]);
        }

        return InventoryMovement::create([
            'warehouse_id' => $inventory->warehouse_id,
            'product_variant_id' => $inventory->product_variant_id,
            'type' => $type,
            'quantity' => $signedQuantity,
            'balance_after' => (int) $inventory->quantity,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'reference_code' => $referenceCode,
            'reason' => $reason,
            'created_by' => $userId,
            'created_at' => now(),
        ]);
    }
}
