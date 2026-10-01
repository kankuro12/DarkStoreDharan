<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Imports\ProductImporter;
use App\Filament\Resources\Products\ProductResource;
use App\Support\WarehouseScope;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(ProductImporter::class)
                ->label('Import Products')
                ->color('gray'),
            CreateAction::make(),
        ];
    }

    public static function canAccess(array $parameters = []): bool
    {
        return ! WarehouseScope::managedOnly();
    }
}
