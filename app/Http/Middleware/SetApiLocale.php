<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetApiLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Priority: X-Locale header > Accept-Language header > Default (en)
        $locale = 'en'; // Default locale

        // Check for X-Locale header first (custom header)
        if ($request->hasHeader('X-Locale')) {
            $headerLocale = $request->header('X-Locale');
            if (in_array($headerLocale, ['en', 'ar'])) {
                $locale = $headerLocale;
            }
        } 
        // Check Accept-Language header
        elseif ($request->hasHeader('Accept-Language')) {
            $acceptLanguage = $request->header('Accept-Language');
            // Parse Accept-Language header (e.g., "ar,en;q=0.9" or "ar")
            $languages = explode(',', $acceptLanguage);
            $primaryLanguage = trim(explode(';', $languages[0])[0]);
            
            if (in_array($primaryLanguage, ['en', 'ar'])) {
                $locale = $primaryLanguage;
            }
        }

        // Set the application locale
        app()->setLocale($locale);

        // Add locale to response header
        $response = $next($request);
        
        if ($response instanceof \Illuminate\Http\JsonResponse || $response instanceof \Symfony\Component\HttpFoundation\JsonResponse) {
            $response->headers->set('Content-Language', $locale);
        }

        return $response;
    }
}
