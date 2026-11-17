<?php

namespace App\Providers\Filament;

use App\Filament\Rider\Pages\Auth\RiderRegister;
use App\Filament\Rider\Pages\MyDeliveries;
use App\Filament\Rider\Pages\RiderAccountStatus;
use App\Filament\Rider\Pages\RiderOnboarding;
use App\Filament\Shared\PhoneOrEmailLogin;
use App\Http\Middleware\EnsureRiderApproved;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class RiderPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('rider')
            ->path('rider')
            ->login(PhoneOrEmailLogin::class)
            ->registration(RiderRegister::class)
            ->passwordReset()
            ->authGuard('delivery')
            ->authPasswordBroker('delivery_agents')
            ->brandName('DarkStore Rider')
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->pages([
                MyDeliveries::class,
                RiderOnboarding::class,
                RiderAccountStatus::class,
            ])
            ->homeUrl(fn (): string => auth('delivery')->user()?->isApproved() ? MyDeliveries::getUrl() : RiderAccountStatus::getUrl())
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureRiderApproved::class,
            ]);
    }
}
