<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\City;
use App\Models\DeliveryAgent;
use App\Models\KycDocument;
use App\Models\Setting;
use App\Models\User;
use App\Services\Cart\CartService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Stable morph aliases for KYC documents / location requests (not enforced: legacy morphs keep class names).
        Relation::morphMap([
            'delivery_agent' => DeliveryAgent::class,
        ]);

        // Separate admin abilities per party (staff `web` guard only).
        Gate::define('manage-riders', fn (User $user) => in_array($user->role, [UserRole::SuperAdmin, UserRole::DeliveryCoordinator], true));
        Gate::define('manage-vendors', fn (User $user) => in_array($user->role, [UserRole::SuperAdmin, UserRole::CatalogManager], true));
        Gate::define('manage-vendor-payouts', fn (User $user) => in_array($user->role, [UserRole::SuperAdmin, UserRole::Finance], true));
        Gate::define('view-kyc-documents', fn (User $user, KycDocument $document) => Gate::forUser($user)->allows(
            $document->documentable_type === 'vendor' ? 'manage-vendors' : 'manage-riders'
        ));

        View::composer(['layouts.storefront', 'storefront.*'], function ($view) {
            $cartService = app(CartService::class);
            $allCities = City::active()->get();
            $data = $view->getData();

            if (array_key_exists('currentCity', $data)) {
                $currentCity = $data['currentCity'];
            } else {
                $cityId = session('cart.city_id') ?? $cartService->getSelectedCityId();
                $currentCity = $cityId ? $allCities->firstWhere('id', (int) $cityId) : null;
            }

            if (Schema::hasTable('settings')) {
                $storeSettings = Cache::rememberForever('store_settings', function () {
                    return Setting::all()->pluck('value', 'key')->toArray();
                });
            } else {
                $storeSettings = [];
            }

            $view->with([
                'allCities' => $allCities,
                'currentCity' => $currentCity,
                'cartItemCount' => $cartService->getItemCount(),
                'cartDetailed' => $cartService->getDetailedCart($currentCity?->id),
                'storeSettings' => $storeSettings,
            ]);
        });
    }
}
