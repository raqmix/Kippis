<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Skip security headers for Filament asset routes to avoid interfering with asset serving
        if ($this->isFilamentAssetRoute($request)) {
            return $next($request);
        }

        $response = $next($request);

        // Use headers->set() which works for all response types including BinaryFileResponse
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' blob:; style-src 'self' 'unsafe-inline'; img-src 'self' https://ui-avatars.com https://*.amazonaws.com data:; font-src 'self' data:; connect-src 'self' https://www.google-analytics.com;");
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        return $response;
    }

    /**
     * Check if the request is for a Filament asset route
     */
    protected function isFilamentAssetRoute(Request $request): bool
    {
        $path = $request->path();
        
        // Check for Filament asset routes (CSS, JS, fonts, etc.)
        return str_starts_with($path, 'css/') 
            || str_starts_with($path, 'js/')
            || str_starts_with($path, 'fonts/')
            || str_contains($path, '/assets/')
            || str_contains($path, 'filament/');
    }
}

