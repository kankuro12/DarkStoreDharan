<?php

namespace App\Filament\Resources\VendorPayouts\Pages;

use App\Filament\Resources\VendorPayouts\VendorPayoutResource;
use App\Models\AuditLog;
use App\Models\VendorPayout;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageVendorPayouts extends ManageRecords
{
    protected static string $resource = VendorPayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Record payout')
                ->mutateDataUsing(fn (array $data) => array_merge($data, ['recorded_by' => auth()->id()]))
                ->after(fn (VendorPayout $record) => AuditLog::record('vendor_payout_recorded', $record, null, [
                    'vendor_id' => $record->vendor_id,
                    'amount' => (string) $record->amount,
                    'reference' => $record->reference,
                ])),
        ];
    }
}
