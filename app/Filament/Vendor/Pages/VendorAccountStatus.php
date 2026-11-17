<?php

namespace App\Filament\Vendor\Pages;

use App\Filament\Shared\ReuploadExpiredAction;
use App\Models\Vendor;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class VendorAccountStatus extends Page
{
    protected string $view = 'filament.shared.kyc-account-status';

    protected static ?string $title = 'Account status';

    protected static ?string $slug = 'account-status';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected function getHeaderActions(): array
    {
        return [ReuploadExpiredAction::make(fn () => auth('vendor')->user(), fn () => VendorOnboarding::getUrl())];
    }

    protected function getViewData(): array
    {
        /** @var Vendor $vendor */
        $vendor = auth('vendor')->user();

        return [
            'party' => $vendor,
            'documents' => $vendor->kycDocuments()->orderBy('type')->get(),
            'onboardingUrl' => VendorOnboarding::getUrl(),
        ];
    }
}
