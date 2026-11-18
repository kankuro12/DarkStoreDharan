<?php

namespace App\Filament\Resources\DeliveryZones\Pages;

use App\Filament\Resources\DeliveryZones\DeliveryZoneResource;
use App\Support\WarehouseScope;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDeliveryZones extends ManageRecords
{
    protected static string $resource = DeliveryZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add area'),
        ];
    }

    public static function canAccess(array $parameters = []): bool
    {
        return ! WarehouseScope::managedOnly();
    }
}
