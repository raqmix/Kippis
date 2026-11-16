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

        // Payment gateway returns / callbacks carry no client headers: they
        // must keep the platform the cart was priced with.
        if ($request->is('api/v1/payment/mastercard/3ds-return/*', 'api/v1/webhooks/*')) {
            $request->attributes->set(ClientPlatform::PASSIVE_ATTRIBUTE, true);
        }

        return $next($request);
    }
}
