<?php

namespace App\Providers;

use App\Models\City;
use App\Models\DeliveryAgent;
use App\Models\Setting;
use App\Services\Cart\CartService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Cache;
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
