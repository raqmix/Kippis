<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            \Illuminate\Support\Facades\Route::middleware(['web', 'auth:admin'])
                ->prefix('admin/notifications')
                ->name('filament.admin.notifications.')
                ->group(function () {
                    \Illuminate\Support\Facades\Route::post('/{notification}/mark-read', [\App\Http\Controllers\NotificationController::class, 'markAsRead'])
                        ->name('mark-read');
                    \Illuminate\Support\Facades\Route::post('/mark-all-read', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead'])
                        ->name('mark-all-read');
                });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global security headers
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        
        // API locale middleware
        $middleware->alias([
            'api.locale' => \App\Http\Middleware\SetApiLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle API exceptions
        $exceptions->render(function (\App\Http\Exceptions\ApiException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => $e->getErrorCode(),
                        'message' => $e->getMessage(),
                    ],
                ], $e->getStatusCode());
            }
        });

        // Handle validation errors for API
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->is('api/*')) {
                // Ensure locale is set for API requests
                if (!$request->hasHeader('X-Locale') && !$request->hasHeader('Accept-Language')) {
                    app()->setLocale('en'); // Default to English if no header
                }
                
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => __('api.validation_failed'),
                        'errors' => $e->errors(),
                    ],
                ], 422);
            }
        });
    })->create();
