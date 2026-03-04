<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiExpectsJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $accept = $request->header('Accept', '');
        if ($request->is('api/*') && (empty($accept) || !str_contains($accept, 'json'))) {
            $request->headers->set('Accept', 'application/json');
        }

        return $next($request);
    }
}
