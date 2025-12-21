<?php

namespace App\Http\Middleware;

use App\Core\Events\AdminLoggedIn;
use App\Core\Events\FailedLoginAttempt;
use App\Core\Models\AdminLoginHistory;
use App\Core\Models\LoginAttempt;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackLoginAttempts
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Track login attempts for Filament login route
        // Check both route name and path to catch all login attempts
        $isLoginRoute = $request->routeIs('filament.admin.auth.login') || 
                       ($request->is('admin/login') && $request->isMethod('post'));
        
        if ($isLoginRoute) {
            $email = $request->input('email') ?? $request->input('login');
            $ipAddress = $request->ip();
            $userAgent = $request->userAgent();
            $isSuccessful = auth('admin')->check();
            $admin = auth('admin')->user();

            // Get email from admin if login was successful, otherwise use provided email
            $email = $isSuccessful && $admin ? $admin->email : ($email ?: null);

            // Create login attempt record
            $loginAttempt = LoginAttempt::create([
                'email' => $email,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'success' => $isSuccessful,
                'failure_reason' => $isSuccessful ? null : 'Invalid credentials',
                'attempted_at' => now(),
            ]);

            if ($isSuccessful && $admin) {
                // Create login history
                AdminLoginHistory::create([
                    'admin_id' => $admin->id,
                    'login_at' => now(),
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'success' => true,
                ]);

                // Fire AdminLoggedIn event
                event(new AdminLoggedIn($admin, $ipAddress, $userAgent));
            } else {
                // Fire FailedLoginAttempt event for security logs
                event(new FailedLoginAttempt(
                    $email ?? 'unknown',
                    $ipAddress,
                    $userAgent,
                    'Invalid credentials'
                ));
            }
        }

        return $response;
    }
}
