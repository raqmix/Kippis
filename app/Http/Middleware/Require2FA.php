<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Require2FA
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('admin');
        
        if ($admin && $admin->two_factor_enabled && !$admin->two_factor_confirmed_at) {
            return redirect()->route('filament.admin.auth.two-factor');
        }
        
        return $next($request);
    }
}
