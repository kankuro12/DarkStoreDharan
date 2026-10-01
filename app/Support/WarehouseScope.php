<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Warehouse;

/**
 * Single-warehouse confinement for warehouse managers.
 *
 * A staff user with the warehouse_manager role and an assigned warehouse_id may
 * only ever see and touch that warehouse's data. Everyone else (super admins,
 * other staff roles, console, guests) is unrestricted and gets null here.
 */
class WarehouseScope
{
    public static function managedWarehouseId(): ?int
    {
        $user = auth()->user();

        if (! $user instanceof User || $user->role !== UserRole::WarehouseManager) {
            return null;
        }

        return $user->warehouse_id;
    }

    public static function managedOnly(): bool
    {
        return self::managedWarehouseId() !== null;
    }

    public static function canAccessWarehouse(?int $warehouseId): bool
    {
        $managed = self::managedWarehouseId();

        return $managed === null || (int) $warehouseId === $managed;
    }

    public static function managedWarehouseName(): ?string
    {
        $id = self::managedWarehouseId();

        return $id ? (string) Warehouse::whereKey($id)->value('name') : null;
    }
}
