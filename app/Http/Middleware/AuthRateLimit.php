<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class AuthRateLimit
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get the rate limit key and limit for this request
        $key = $this->getKey($request);
        $limit = $this->getLimit($request);

        if ($key && $limit) {
            if (RateLimiter::tooManyAttempts($key, $limit['attempts'])) {
                $seconds = RateLimiter::availableIn($key);

                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'RATE_LIMIT_EXCEEDED',
                        'message' => "Too many attempts. Please try again in {$seconds} seconds.",
                    ],
                ], 429);
            }

            RateLimiter::hit($key, $limit['decay']);
        }

        return $next($request);
    }

    /**
     * Get the rate limit key for the request.
     */
    protected function getKey(Request $request): ?string
    {
        if ($request->is('api/v1/auth/login')) {
            return 'auth:login:' . $request->ip();
        }

        if ($request->is('api/v1/auth/register')) {
            return 'auth:register:' . $request->ip();
        }

        if ($request->is('api/v1/auth/verify')) {
            $email = $request->input('email', '');
            return 'auth:verify:' . md5($email);
        }

        if ($request->is('api/v1/auth/forgot-password')) {
            $email = $request->input('email', '');
            return 'auth:forgot:' . md5($email);
        }

        if ($request->is('api/v1/auth/resend-otp')) {
            $email = $request->input('email', '');
            return 'auth:resend:' . md5($email);
        }

        if ($request->is('api/v1/auth/reset-password')) {
            $email = $request->input('email', '');
            return 'auth:reset:' . md5($email);
        }

        return null;
    }

    /**
     * Get the rate limit configuration for the request.
     */
    protected function getLimit(Request $request): ?array
    {
        if ($request->is('api/v1/auth/login')) {
            return ['attempts' => 5, 'decay' => 900]; // 5 attempts per 15 minutes
        }

        if ($request->is('api/v1/auth/register')) {
            return ['attempts' => 3, 'decay' => 900]; // 3 attempts per 15 minutes
        }

        if ($request->is('api/v1/auth/verify')) {
            return ['attempts' => 10, 'decay' => 900]; // 10 attempts per 15 minutes
        }

        if ($request->is('api/v1/auth/forgot-password')) {
            return ['attempts' => 5, 'decay' => 900]; // 5 attempts per 15 minutes
        }

        if ($request->is('api/v1/auth/resend-otp')) {
            return ['attempts' => 5, 'decay' => 900]; // 5 attempts per 15 minutes
        }

        if ($request->is('api/v1/auth/reset-password')) {
            return ['attempts' => 5, 'decay' => 900]; // 5 attempts per 15 minutes
        }

        return null;
    }
}
