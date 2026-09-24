<?php

namespace App\Services\Fulfillment;

use App\Exceptions\OutOfStockException;
use App\Models\City;
use App\Models\Inventory;
use App\Models\Warehouse;

class WarehouseSelector
{
    /**
     * Find the best warehouse capable of fulfilling 100% of the given items.
     * Items format: [ ['variant_id' => 1, 'quantity' => 2], ... ]
     *
     * @throws OutOfStockException
     */
    public function selectWarehouseForOrder(int $cityId, array $items): Warehouse
    {
        $city = City::with(['warehouses' => function ($q) {
            $q->wherePivot('status', 'active')
                ->where('warehouses.status', 'active')
                ->orderByPivot('priority', 'asc');
        }])->findOrFail($cityId);

        $warehouses = $city->warehouses;

        if ($warehouses->isEmpty()) {
            throw new OutOfStockException('No active fulfillment center is currently configured for '.$city->name);
        }

        $unavailableItemsMap = [];

        foreach ($warehouses as $warehouse) {
            $canFulfillAll = true;
            $missingInThisWarehouse = [];

            foreach ($items as $item) {
                $variantId = (int) $item['variant_id'];
                $requiredQty = (int) $item['quantity'];

                $inventory = Inventory::where('warehouse_id', $warehouse->id)
                    ->where('product_variant_id', $variantId)
                    ->first();

                $available = $inventory ? $inventory->available : 0;

                if ($available < $requiredQty) {
                    $canFulfillAll = false;
                    $missingInThisWarehouse[] = [
                        'variant_id' => $variantId,
                        'required' => $requiredQty,
                        'available' => $available,
                        'warehouse' => $warehouse->name,
                    ];
                }
            }

            if ($canFulfillAll) {
                return $warehouse;
            }

            $unavailableItemsMap[$warehouse->id] = $missingInThisWarehouse;
        }

        // If no single warehouse can fulfill 100%
        $firstWarehouseMissing = reset($unavailableItemsMap) ?: [];

        throw new OutOfStockException(
            "Some items in your cart cannot be fully fulfilled from our {$city->name} warehouses.",
            $firstWarehouseMissing
        );
    }
}
