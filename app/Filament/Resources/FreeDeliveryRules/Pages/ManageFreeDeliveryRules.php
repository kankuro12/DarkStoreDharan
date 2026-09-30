<?php

namespace App\Filament\Resources\FreeDeliveryRules\Pages;

use App\Filament\Resources\FreeDeliveryRules\FreeDeliveryRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFreeDeliveryRules extends ManageRecords
{
    protected static string $resource = FreeDeliveryRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
