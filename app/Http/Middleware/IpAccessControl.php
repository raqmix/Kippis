<?php

namespace App\Http\Middleware;

use App\Core\Models\IpAccessRule;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IpAccessControl
{
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();
        
        // Check blacklist
        $blacklisted = IpAccessRule::active()
            ->where('type', 'blacklist')
            ->where('ip_address', $ip)
            ->exists();
            
        if ($blacklisted) {
            abort(403, 'Your IP address has been blocked.');
        }
        
        // Check whitelist (if enabled)
        if (config('security.ip.enable_whitelist', false)) {
            $whitelisted = IpAccessRule::active()
                ->where('type', 'whitelist')
                ->where('ip_address', $ip)
                ->exists();
                
            if (!$whitelisted) {
                abort(403, 'Your IP address is not whitelisted.');
            }
        }
        
        return $next($request);
    }
}

