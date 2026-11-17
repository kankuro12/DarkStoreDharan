<?php

namespace App\Filament\Resources\LocationChangeRequests\Pages;

use App\Enums\LocationChangeStatus;
use App\Filament\Resources\LocationChangeRequests\LocationChangeRequestResource;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageLocationChangeRequests extends ManageRecords
{
    protected static string $resource = LocationChangeRequestResource::class;

    public function getTabs(): array
    {
        return [
            // Filament injects closure arguments by name: it must be `$query`.
            'pending' => Tab::make('Pending')->modifyQueryUsing(fn (Builder $query) => $query->where('status', LocationChangeStatus::Pending)),
            'all' => Tab::make('All'),
        ];
    }
}
