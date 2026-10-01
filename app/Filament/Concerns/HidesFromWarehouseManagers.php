<?php

namespace App\Filament\Concerns;

use App\Support\WarehouseScope;

/**
 * Global configuration screens (catalog, cities, coupons, settings, agents, …)
 * are super-admin territory. Warehouse managers work inside their own store's
 * operational screens only, so these are hidden from their navigation, global
 * search results and direct URLs (each page also gates `canAccess`).
 */
trait HidesFromWarehouseManagers
{
    public static function shouldRegisterNavigation(): bool
    {
        return ! WarehouseScope::managedOnly();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return WarehouseScope::managedOnly() ? [] : parent::getGloballySearchableAttributes();
    }
}
