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

