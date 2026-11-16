<?php

namespace App\Http\Middleware;

use App\Support\ClientPlatform;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tags every API request with its client platform (mobile|web|kiosk|unknown)
 * so cart pricing can tell app orders apart. See ClientPlatform::resolve().
 */
class ResolveClientPlatform
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(ClientPlatform::ATTRIBUTE, ClientPlatform::resolve($request));

        return $next($request);
    }
}
