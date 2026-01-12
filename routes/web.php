<?php

use App\Http\Controllers\Admin\OrderController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Admin routes for Filament
Route::middleware(['auth:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/orders/{order}/download-pdf', [OrderController::class, 'downloadPdf'])->name('orders.download-pdf');
});
