<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\DeliveryAgent;
use App\Models\Order;
use App\Services\Delivery\DeliveryOfferService;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            OrderResource::updateStatusAction(),
            OrderResource::cancelOrderAction(),
            EditAction::make()
                ->label('Edit Rider / Notes')
                // Rider changes go through the offer service so pay, status and audit stay consistent.
                ->using(function (Order $record, array $data): Order {
                    $record->update(['notes' => $data['notes'] ?? $record->notes]);

                    $newRiderId = $data['delivery_agent_id'] ?? null;
                    if ((int) $newRiderId === (int) $record->delivery_agent_id) {
                        return $record;
                    }

                    $offers = app(DeliveryOfferService::class);

                    if (! $newRiderId) {
                        $offers->unassign($record, auth()->id());

                        return $record;
                    }

                    $rider = DeliveryAgent::eligibleForDispatch()->where('city_id', $record->city_id)->findOrFail($newRiderId);
                    if (! $offers->assignManually($record, $rider, auth()->id())) {
                        Notification::make()->title('The rider changed while you were editing. Reload and try again.')->warning()->send();
                    }

                    return $record->refresh();
                }),
        ];
    }
}
