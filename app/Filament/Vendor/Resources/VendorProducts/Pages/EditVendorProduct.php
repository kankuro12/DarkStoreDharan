<?php

namespace App\Filament\Vendor\Resources\VendorProducts\Pages;

use App\Filament\Vendor\Resources\VendorProducts\VendorProductResource;
use Filament\Resources\Pages\EditRecord;

class EditVendorProduct extends EditRecord
{
    protected static string $resource = VendorProductResource::class;

    /** Any vendor edit goes back to staff review; status/vendor/slug are never vendor-controlled. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['vendor_id'], $data['slug']);

        return array_merge($data, ['status' => 'pending_review']);
    }
}
