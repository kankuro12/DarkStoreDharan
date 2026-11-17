<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Until approved, a self-onboarding party may only use its panel's verification and status pages. */
abstract class EnsureKycApproved
{
    abstract protected function guard(): string;

    abstract protected function panelId(): string;

    abstract protected function onboardingUrl(): string;

    abstract protected function statusUrl(): string;

    /** Extra routes an unapproved party may still use (e.g. a suspended rider finishing deliveries). */
    protected function allowsWhileUnapproved(Request $request, mixed $party): bool
    {
        return false;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $party = auth($this->guard())->user();
        $panel = $this->panelId();

        if ($party
            && ! $party->isApproved()
            && ! $request->hasHeader('X-Livewire')
            && ! $this->allowsWhileUnapproved($request, $party)
            && ! $request->routeIs("filament.{$panel}.pages.onboarding", "filament.{$panel}.pages.account-status", "filament.{$panel}.auth.*")) {
            return redirect($party->verification_status->isEditable() ? $this->onboardingUrl() : $this->statusUrl());
        }

        return $next($request);
    }
}
