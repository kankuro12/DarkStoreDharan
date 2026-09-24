<?php

use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\HealthCheckController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentCallbackController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

// Cities & Delivery Zones
Route::get('/cities', [CityController::class, 'index']);
Route::get('/cities/{city}', [CityController::class, 'show']);

// Categories & Catalog (City-Scoped)
Route::get('/categories', [ProductController::class, 'categories']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{slug}', [ProductController::class, 'show']);

// Cart & City Switch Revalidation
Route::prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'index']);
    Route::post('/items', [CartController::class, 'addItem']);
    Route::patch('/items/{variantId}', [CartController::class, 'updateItem']);
    Route::delete('/items/{variantId}', [CartController::class, 'removeItem']);
    Route::post('/change-city', [CartController::class, 'changeCity']);
    Route::post('/coupon', [CartController::class, 'applyCoupon']);
    Route::delete('/coupon', [CartController::class, 'removeCoupon']);
});

// Checkout Validation & Orders
Route::post('/checkout/validate', [CheckoutController::class, 'validateCheckout']);
Route::post('/orders', [CheckoutController::class, 'placeOrder']);

Route::prefix('orders')->group(function () {
    Route::get('/{order}', [OrderController::class, 'show']);
    Route::post('/{order}/cancel', [OrderController::class, 'cancel']);
    Route::get('/{order}/tracking', [OrderController::class, 'tracking']);
});

// Payment Gateways Callback & Webhook Verification
Route::post('/payments/callback', [PaymentCallbackController::class, 'handleCallback']);

// System Health & Observability (§44, §46)
Route::get('/health', [HealthCheckController::class, 'index']);
