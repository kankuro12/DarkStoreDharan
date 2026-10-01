<?php

namespace App\Filament\Resources\InventoryMovements\Pages;

use App\Filament\Resources\InventoryMovements\InventoryMovementResource;
use App\Support\WarehouseScope;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class ListInventoryMovements extends ListRecords
{
    protected static string $resource = InventoryMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getTableQuery(): Builder|Relation|null
    {
        $query = parent::getTableQuery();
        $managed = WarehouseScope::managedWarehouseId();

        return $managed ? $query?->where('inventory_movements.warehouse_id', $managed) : $query;
    }
}
