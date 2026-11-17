<?php

namespace App\Http\Middleware;

use App\Filament\Rider\Pages\RiderAccountStatus;
use App\Filament\Rider\Pages\RiderOnboarding;
use App\Models\DeliveryAgent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Until approved, a rider may only use the verification and status pages of the rider panel. */
class EnsureRiderApproved
{
    public const OPEN_ROUTES = [
        'filament.rider.pages.onboarding',
        'filament.rider.pages.account-status',
        'filament.rider.auth.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $rider = auth('delivery')->user();

        if ($rider instanceof DeliveryAgent
            && ! $rider->isApproved()
            && ! $request->hasHeader('X-Livewire')
            && ! $request->routeIs(self::OPEN_ROUTES)) {
            return redirect($rider->verification_status->isEditable() ? RiderOnboarding::getUrl() : RiderAccountStatus::getUrl());
        }

        return $next($request);
    }
}
