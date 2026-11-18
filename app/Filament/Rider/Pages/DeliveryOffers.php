<?php

namespace App\Filament\Rider\Pages;

use App\Models\Order;
use App\Services\Delivery\DeliveryOfferService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class DeliveryOffers extends Page
{
    protected string $view = 'filament.rider.pages.delivery-offers';

    protected static ?string $navigationLabel = 'Offers';

    protected static ?string $title = 'Delivery Offers';

    protected static ?int $navigationSort = -1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    public static function canAccess(): bool
    {
        return (bool) auth('delivery')->user()?->isApproved();
    }

    public function isOnline(): bool
    {
        return auth('delivery')->user()->fresh()->availability === 'available';
    }

    /** @return Collection<int, Order> */
    public function getOffersProperty(): Collection
    {
        return app(DeliveryOfferService::class)->openOffersFor(auth('delivery')->user()->fresh());
    }

    public function acceptOffer(int $orderId): void
    {
        $order = Order::find($orderId);

        if (! $order || ! app(DeliveryOfferService::class)->accept($order, auth('delivery')->user()->fresh())) {
            Notification::make()->title('Already taken or not available')->warning()->send();

            return;
        }

        Notification::make()->title('Order accepted')->success()->send();
        $this->redirect(MyDeliveries::getUrl());
    }

    public function declineOffer(int $orderId): void
    {
        if ($order = Order::find($orderId)) {
            app(DeliveryOfferService::class)->decline($order, auth('delivery')->user());
        }
    }

    public function toggleOnline(): void
    {
        $rider = auth('delivery')->user()->fresh();

        if ($this->isOnline()) {
            if (! $rider->goOffline()) {
                Notification::make()->title('Finish or hand back your current deliveries before going offline')->warning()->send();
            }

            return;
        }

        $rider->goOnline();
    }
}
