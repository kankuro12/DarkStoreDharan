<?php

namespace App\Filament\Resources\AuditLogs\Pages;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Support\WarehouseScope;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAuditLogs extends ManageRecords
{
    protected static string $resource = AuditLogResource::class;

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
