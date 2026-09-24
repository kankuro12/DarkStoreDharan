<?php

namespace App\Providers;

use App\Models\City;
use App\Services\Cart\CartService;
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

            $view->with([
                'allCities' => $allCities,
                'currentCity' => $currentCity,
                'cartItemCount' => $cartService->getItemCount(),
                'cartDetailed' => $cartService->getDetailedCart($currentCity?->id),
            ]);
        });
    }
}
