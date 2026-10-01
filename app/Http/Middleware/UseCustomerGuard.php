<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Storefront routes (routes/web.php) authenticate against the `customer` guard
 * (customers table). Admin stays on `web` (users table) and riders on `delivery`
 * (delivery_agents table) — Filament panels run outside the web middleware group,
 * so this only affects the storefront.
 *
 * Livewire's shared /livewire/update endpoint IS part of the web group and is
 * used by both Filament panels, so panel update calls keep their own guard.
 */
class UseCustomerGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasHeader('X-Livewire') && ! $request->is('admin*') && ! $request->is('rider*')) {
            Auth::shouldUse('customer');
        }

        return $next($request);
    }
}
