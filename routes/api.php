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
});

