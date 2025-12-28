<?php

use App\Http\Controllers\Api\V1\CustomerAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('api.locale')->group(function () {
    Route::prefix('v1/customers')->group(function () {
        // Public routes
        Route::post('/register', [CustomerAuthController::class, 'register']);
        Route::post('/verify', [CustomerAuthController::class, 'verify']);
        Route::post('/login', [CustomerAuthController::class, 'login']);
        Route::post('/forgot-password', [CustomerAuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [CustomerAuthController::class, 'resetPassword']);
        Route::post('/resend-otp', [CustomerAuthController::class, 'resendOtp'])->name('resend-otp');
        
        // Authenticated routes
        Route::middleware('auth:api')->group(function () {
            Route::get('/me', [CustomerAuthController::class, 'me']);
            Route::post('/logout', [CustomerAuthController::class, 'logout']);
            Route::post('/refresh-token', [CustomerAuthController::class, 'refreshToken']);
            Route::delete('/account', [CustomerAuthController::class, 'deleteAccount']);
        });
    });

    // Stores routes (public)
    Route::prefix('v1/stores')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\V1\StoreController::class, 'index']);
        Route::get('/{id}', [\App\Http\Controllers\Api\V1\StoreController::class, 'show']);
    });

    // Support Tickets routes
    Route::prefix('v1/support-tickets')->group(function () {
        Route::post('/', [\App\Http\Controllers\Api\V1\SupportTicketController::class, 'store']);
        Route::get('/', [\App\Http\Controllers\Api\V1\SupportTicketController::class, 'index']);
        Route::get('/{id}', [\App\Http\Controllers\Api\V1\SupportTicketController::class, 'show']);
    });

    // Home API (public)
    Route::get('/v1/home', [\App\Http\Controllers\Api\V1\HomeController::class, 'index']);

    // Categories & Products (public)
    Route::prefix('v1/categories')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\V1\CategoryController::class, 'index']);
    });

    Route::prefix('v1/products')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\V1\ProductController::class, 'index']);
        Route::get('/{id}', [\App\Http\Controllers\Api\V1\ProductController::class, 'show']);
    });

    // Mix Builder (public)
    Route::prefix('v1/mix')->group(function () {
        Route::get('/options', [\App\Http\Controllers\Api\V1\MixController::class, 'options']);
        Route::post('/preview', [\App\Http\Controllers\Api\V1\MixController::class, 'preview']);
    });

    // Cart APIs (authenticated or guest)
    Route::prefix('v1/cart')->group(function () {
        Route::post('/init', [\App\Http\Controllers\Api\V1\CartController::class, 'init']);
        Route::get('/', [\App\Http\Controllers\Api\V1\CartController::class, 'index']);
        Route::post('/items', [\App\Http\Controllers\Api\V1\CartController::class, 'addItem']);
        Route::patch('/items/{id}', [\App\Http\Controllers\Api\V1\CartController::class, 'updateItem']);
        Route::delete('/items/{id}', [\App\Http\Controllers\Api\V1\CartController::class, 'removeItem']);
        Route::post('/apply-promo', [\App\Http\Controllers\Api\V1\CartController::class, 'applyPromo']);
        Route::post('/remove-promo', [\App\Http\Controllers\Api\V1\CartController::class, 'removePromo']);
        Route::post('/abandon', [\App\Http\Controllers\Api\V1\CartController::class, 'abandon']);
    });

    // Checkout (requires authentication)
    Route::middleware('auth:api')->post('/v1/checkout', [\App\Http\Controllers\Api\V1\OrderController::class, 'checkout']);

    // Orders (authenticated)
    Route::middleware('auth:api')->prefix('v1/orders')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\V1\OrderController::class, 'index']);
        Route::get('/{id}', [\App\Http\Controllers\Api\V1\OrderController::class, 'show']);
        Route::get('/{id}/tracking', [\App\Http\Controllers\Api\V1\OrderController::class, 'tracking']);
        Route::post('/{id}/reorder', [\App\Http\Controllers\Api\V1\OrderController::class, 'reorder']);
    });

    // Loyalty (authenticated)
    Route::middleware('auth:api')->prefix('v1/loyalty')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\V1\LoyaltyController::class, 'index']);
    });

    // QR Receipts (authenticated)
    Route::middleware('auth:api')->prefix('v1/qr')->group(function () {
        Route::post('/scan', [\App\Http\Controllers\Api\V1\QrReceiptController::class, 'scan']);
        Route::post('/manual', [\App\Http\Controllers\Api\V1\QrReceiptController::class, 'manual']);
        Route::get('/history', [\App\Http\Controllers\Api\V1\QrReceiptController::class, 'history']);
    });
});

