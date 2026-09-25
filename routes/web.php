<?php

use App\Enums\UserRole;
use App\Http\Controllers\Storefront\AuthController;
use App\Http\Controllers\Storefront\CartWebController;
use App\Http\Controllers\Storefront\CheckoutWebController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\OrderTrackingWebController;
use App\Http\Controllers\Storefront\ShareController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

// Storefront Home & Catalog Browsing
Route::get('/', [HomeController::class, 'index'])->name('storefront.home');
Route::get('/products/{slug}', [HomeController::class, 'product'])->name('storefront.product');
Route::get('/share/product/{slug}', [ShareController::class, 'product'])->name('storefront.share.product');

// Contact Page
Route::view('/contact', 'storefront.contact')->name('storefront.contact');

// Authentication & Social Login
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    // Socialite
    Route::get('/auth/{provider}/redirect', [AuthController::class, 'redirectProvider'])->name('social.redirect');
    Route::get('/auth/{provider}/callback', [AuthController::class, 'handleProviderCallback'])->name('social.callback');
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::get('/dashboard', function () {
    return redirect()->route('storefront.home');
})->middleware('auth');

// Cart Actions (Web Session)
Route::prefix('cart')->group(function () {
    Route::post('/add', [CartWebController::class, 'add'])->name('storefront.cart.add');
    Route::post('/update', [CartWebController::class, 'update'])->name('storefront.cart.update');
    Route::post('/remove', [CartWebController::class, 'remove'])->name('storefront.cart.remove');
    Route::post('/change-city', [CartWebController::class, 'changeCity'])->name('storefront.cart.change_city');
    Route::post('/coupon', [CartWebController::class, 'applyCoupon'])->name('storefront.cart.coupon');
    Route::delete('/coupon', [CartWebController::class, 'removeCoupon'])->name('storefront.cart.remove_coupon');
});

// Checkout Stepper
Route::get('/checkout', [CheckoutWebController::class, 'show'])->name('storefront.checkout.show');
Route::post('/checkout', [CheckoutWebController::class, 'process'])->name('storefront.checkout.process');

// Live Order Tracking & Return Requests
Route::get('/orders/{orderNumber}/tracking', [OrderTrackingWebController::class, 'show'])->name('storefront.order.tracking');
Route::post('/orders/{orderNumber}/return', [OrderTrackingWebController::class, 'requestReturn'])->name('storefront.order.return');

// Legal & Compliance Pages (§48)
Route::view('/privacy-policy', 'storefront.privacy')->name('storefront.privacy');
Route::view('/terms-of-service', 'storefront.terms')->name('storefront.terms');

// Local Environment Development Helper
if (app()->environment('local')) {
    Route::get('/local-admin-login', function () {
        $user = User::where('role', UserRole::SuperAdmin)->first()
            ?? User::first();
        if ($user) {
            auth()->login($user);
        }

        return redirect(request()->query('redirect', '/admin/warehouse-operations'));
    });
}
