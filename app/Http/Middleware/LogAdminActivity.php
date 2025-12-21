<?php

namespace App\Http\Middleware;

use App\Core\Services\ActivityLogService;
use App\Core\Enums\ActivityAction;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogAdminActivity
{
    public function __construct(
        private ActivityLogService $activityLogService
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Log activity for authenticated admin requests
        if ($request->user('admin') && $request->method() !== 'GET') {
            $this->activityLogService->log(
                ActivityAction::UPDATE,
                null,
                null,
                null,
                $request->user('admin')->id
            );
        }

        return $response;
    }
}
