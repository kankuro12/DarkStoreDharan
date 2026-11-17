<?php

namespace App\Http\Middleware;

use App\Filament\Rider\Pages\RiderAccountStatus;
use App\Filament\Rider\Pages\RiderOnboarding;

class EnsureRiderApproved extends EnsureKycApproved
{
    protected function guard(): string
    {
        return 'delivery';
    }

    protected function panelId(): string
    {
        return 'rider';
    }

    protected function onboardingUrl(): string
    {
        return RiderOnboarding::getUrl();
    }

    protected function statusUrl(): string
    {
        return RiderAccountStatus::getUrl();
    }
}
