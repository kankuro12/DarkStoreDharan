<?php

namespace App\Filament\Vendor\Resources\VendorProducts\Pages;

use App\Filament\Vendor\Resources\VendorProducts\VendorProductResource;
use App\Models\Product;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateVendorProduct extends CreateRecord
{
    protected static string $resource = VendorProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $slug = Str::slug($data['name']);

        return array_merge($data, [
            'vendor_id' => auth('vendor')->id(),
            'status' => 'pending_review',
            'slug' => Product::where('slug', $slug)->exists() ? $slug.'-'.Str::lower(Str::random(5)) : $slug,
        ]);
    }
}
