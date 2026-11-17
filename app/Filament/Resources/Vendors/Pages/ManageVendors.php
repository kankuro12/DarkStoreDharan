<?php

namespace App\Filament\Resources\Vendors\Pages;

use App\Filament\Resources\Vendors\VendorResource;
use App\Filament\Shared\VerificationTabs;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageVendors extends ManageRecords
{
    protected static string $resource = VendorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Admin-created vendors start as draft and go through the same verification.
            CreateAction::make()->label('Add vendor'),
        ];
    }

    public function getTabs(): array
    {
        return VerificationTabs::make();
    }
}
