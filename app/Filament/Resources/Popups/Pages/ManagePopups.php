<?php

namespace App\Filament\Resources\Popups\Pages;

use App\Filament\Resources\Popups\PopupResource;
use App\Support\WarehouseScope;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePopups extends ManageRecords
{
    protected static string $resource = PopupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public static function canAccess(array $parameters = []): bool
    {
        return ! WarehouseScope::managedOnly();
    }
}
