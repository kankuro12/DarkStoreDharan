<?php

namespace App\Http\Middleware;

use App\Filament\Vendor\Pages\VendorAccountStatus;
use App\Filament\Vendor\Pages\VendorOnboarding;

class EnsureVendorApproved extends EnsureKycApproved
{
    protected function guard(): string
    {
        return 'vendor';
    }

    protected function panelId(): string
    {
        return 'vendor';
    }

    protected function onboardingUrl(): string
    {
        return VendorOnboarding::getUrl();
    }

    protected function statusUrl(): string
    {
        return VendorAccountStatus::getUrl();
    }
}
