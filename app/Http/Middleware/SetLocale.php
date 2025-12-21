<?php

namespace App\Http\Middleware;

use App\Core\Services\LocalizationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $localizationService = app(LocalizationService::class);
        
        // Get authenticated admin
        $admin = Auth::guard('admin')->user();
        
        // Priority: Query parameter > Session > Admin preference > Default
        if ($request->has('locale')) {
            $locale = $request->get('locale');
            if (in_array($locale, ['en', 'ar'])) {
                app()->setLocale($locale);
                session(['locale' => $locale]);
                
                // Update admin's locale if authenticated
                if ($admin) {
                    $admin->update(['locale' => $locale]);
                }
            }
        } elseif (session()->has('locale')) {
            app()->setLocale(session('locale'));
        } elseif ($admin && $admin->locale) {
            $localizationService->setLocale($admin);
        }
        
        return $next($request);
    }
}

