<?php

use App\Http\Controllers\Admin\OrderController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Squad invite landing for `https://kippis-eg.com/squad/join?code=…`. On
// iOS/Android the Universal/App Link is intercepted by the installed
// app before this route is hit; this HTML page is the fallback for
// users without the app installed (web browsers, desktop). It shows the
// code, store badges, and an "Open in app" button that fires the
// universal link again as a manual attempt.
Route::get('/squad/join', function (\Illuminate\Http\Request $request) {
    $code = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) $request->query('code', '')));
    return view('squad-join-fallback', ['code' => $code]);
})->name('squad.join.fallback');

// Admin routes for Filament
Route::middleware(['auth:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/orders/{order}/download-pdf', [OrderController::class, 'downloadPdf'])->name('orders.download-pdf');
});
