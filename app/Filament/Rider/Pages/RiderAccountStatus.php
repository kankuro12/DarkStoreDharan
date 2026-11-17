<?php

namespace App\Filament\Rider\Pages;

use App\Models\DeliveryAgent;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class RiderAccountStatus extends Page
{
    protected string $view = 'filament.shared.kyc-account-status';

    protected static ?string $title = 'Account status';

    protected static ?string $slug = 'account-status';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected function getViewData(): array
    {
        /** @var DeliveryAgent $rider */
        $rider = auth('delivery')->user();

        return [
            'party' => $rider,
            'documents' => $rider->kycDocuments()->orderBy('type')->get(),
            'onboardingUrl' => RiderOnboarding::getUrl(),
        ];
    }
}
