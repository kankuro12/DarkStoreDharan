<?php

namespace App\Filament\Vendor\Resources\VendorProducts\Pages;

use App\Filament\Vendor\Resources\VendorProducts\VendorProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVendorProducts extends ListRecords
{
    protected static string $resource = VendorProductResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Submit a product')];
    }
}
