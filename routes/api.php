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

    // ==================== AUTHENTICATION APIs ====================
    Route::prefix('v1/auth')->group(function () {
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
            Route::put('/me', [CustomerAuthController::class, 'update']);
            Route::patch('/me', [CustomerAuthController::class, 'update']);
            Route::post('/logout', [CustomerAuthController::class, 'logout']);
            Route::post('/refresh-token', [CustomerAuthController::class, 'refreshToken']);
            Route::delete('/account', [CustomerAuthController::class, 'deleteAccount']);
        });
    });

    // ==================== STORES APIs ====================
    Route::prefix('v1/stores')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\V1\StoreController::class, 'index']);
        Route::get('/{id}', [\App\Http\Controllers\Api\V1\StoreController::class, 'show']);
    });

    // ==================== HOME & CATALOG APIs ====================
    Route::prefix('v1/catalog')->group(function () {
        // Home API
        Route::get('/home', [\App\Http\Controllers\Api\V1\HomeController::class, 'index']);

        // Categories
        Route::get('/categories', [\App\Http\Controllers\Api\V1\CategoryController::class, 'index']);

        // Products
        Route::get('/products', [\App\Http\Controllers\Api\V1\ProductController::class, 'index']);
        Route::get('/products/{id}', [\App\Http\Controllers\Api\V1\ProductController::class, 'show']);
    });

    // ==================== MIX BUILDER APIs ====================
    Route::prefix('v1/mix')->group(function () {
        Route::get('/options', [\App\Http\Controllers\Api\V1\MixController::class, 'options']);
        Route::post('/preview', [\App\Http\Controllers\Api\V1\MixController::class, 'preview']);
    });

    // ==================== FRAMES APIs ====================
    Route::prefix('v1/frames')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\V1\FrameController::class, 'index']);
        Route::post('/render', [\App\Http\Controllers\Api\V1\FrameController::class, 'render']);
    });

    // ==================== CART APIs ====================
    Route::middleware('auth:api')->prefix('v1/cart')->group(function () {
        Route::post('/init', [\App\Http\Controllers\Api\V1\CartController::class, 'init']);
        Route::get('/', [\App\Http\Controllers\Api\V1\CartController::class, 'index']);
        Route::post('/items', [\App\Http\Controllers\Api\V1\CartController::class, 'addItem']);
        Route::patch('/items/{id}', [\App\Http\Controllers\Api\V1\CartController::class, 'updateItem']);
        Route::delete('/items/{id}', [\App\Http\Controllers\Api\V1\CartController::class, 'removeItem']);
        Route::post('/apply-promo', [\App\Http\Controllers\Api\V1\CartController::class, 'applyPromo']);
        Route::post('/remove-promo', [\App\Http\Controllers\Api\V1\CartController::class, 'removePromo']);
        Route::post('/abandon', [\App\Http\Controllers\Api\V1\CartController::class, 'abandon']);
    });

    // ==================== PAYMENT METHODS APIs ====================
    Route::prefix('v1/payment-methods')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\V1\PaymentMethodController::class, 'index']);
        Route::get('/{id}', [\App\Http\Controllers\Api\V1\PaymentMethodController::class, 'show']);
    });

    // ==================== ORDERS APIs ====================
    Route::middleware('auth:api')->prefix('v1/orders')->group(function () {
        Route::post('/checkout', [\App\Http\Controllers\Api\V1\OrderController::class, 'checkout']);
        Route::get('/last', [\App\Http\Controllers\Api\V1\OrderController::class, 'lastOrder']);
        Route::get('/', [\App\Http\Controllers\Api\V1\OrderController::class, 'index']);
        Route::get('/{id}/pdf', [\App\Http\Controllers\Api\V1\OrderController::class, 'downloadPdf']);
        Route::get('/{id}/tracking', [\App\Http\Controllers\Api\V1\OrderController::class, 'tracking']);
        Route::get('/{id}', [\App\Http\Controllers\Api\V1\OrderController::class, 'show']);
        Route::post('/{id}/reorder', [\App\Http\Controllers\Api\V1\OrderController::class, 'reorder']);
    });

    // ==================== LOYALTY APIs ====================
    Route::middleware('auth:api')->prefix('v1/loyalty')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\V1\LoyaltyController::class, 'index']);
        Route::post('/redeem', [\App\Http\Controllers\Api\V1\LoyaltyController::class, 'redeem']);
    });

    // ==================== QR CODES APIs ====================
    Route::middleware('auth:api')->prefix('v1/qr-receipts')->group(function () {
        Route::post('/scan', [\App\Http\Controllers\Api\V1\QrReceiptController::class, 'scan']);
    });

    // ==================== CMS PAGES APIs ====================
    Route::prefix('v1/pages')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\V1\PageController::class, 'index']);
        Route::get('/type/{type}', [\App\Http\Controllers\Api\V1\PageController::class, 'getByType']);
        Route::get('/slug/{slug}', [\App\Http\Controllers\Api\V1\PageController::class, 'showBySlug']);
        Route::get('/{id}', [\App\Http\Controllers\Api\V1\PageController::class, 'show']);
    });

    // ==================== SETTINGS APIs ====================
    Route::prefix('v1/settings')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\V1\SettingController::class, 'index']);
        Route::get('/list', [\App\Http\Controllers\Api\V1\SettingController::class, 'list']);
        Route::get('/group/{group}', [\App\Http\Controllers\Api\V1\SettingController::class, 'getByGroup']);
        Route::get('/key/{key}', [\App\Http\Controllers\Api\V1\SettingController::class, 'getByKey']);
        Route::post('/keys', [\App\Http\Controllers\Api\V1\SettingController::class, 'getByKeys']);
    });

    // ==================== SUPPORT TICKETS APIs ====================
    Route::prefix('v1/support')->group(function () {
        Route::post('/tickets', [\App\Http\Controllers\Api\V1\SupportTicketController::class, 'store']);
        Route::get('/tickets', [\App\Http\Controllers\Api\V1\SupportTicketController::class, 'index']);
        Route::get('/tickets/{id}', [\App\Http\Controllers\Api\V1\SupportTicketController::class, 'show']);
    });

    // ==================== LEGACY ROUTES (for backward compatibility) ====================
    // Keep old routes working but redirect to new structure
    Route::get('/v1/home', [\App\Http\Controllers\Api\V1\HomeController::class, 'index']);
    Route::prefix('v1/categories')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\V1\CategoryController::class, 'index']);
    });
    Route::prefix('v1/products')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\V1\ProductController::class, 'index']);
        Route::get('/{id}', [\App\Http\Controllers\Api\V1\ProductController::class, 'show']);
    });
    Route::prefix('v1/qr')->group(function () {
        Route::post('/scan', [\App\Http\Controllers\Api\V1\QrReceiptController::class, 'scan']);
    });
    Route::prefix('v1/support-tickets')->group(function () {
        Route::post('/', [\App\Http\Controllers\Api\V1\SupportTicketController::class, 'store']);
        Route::get('/', [\App\Http\Controllers\Api\V1\SupportTicketController::class, 'index']);
        Route::get('/{id}', [\App\Http\Controllers\Api\V1\SupportTicketController::class, 'show']);
    });
});
