<?php

namespace App\Http\Controllers\Api\Admin;

use App\Core\Models\AnalyticsEvent;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    /**
     * GET /api/admin/analytics/export?event=order.completed&from=2026-04-01&to=2026-04-26&format=csv
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse|JsonResponse
    {
        $request->validate([
            'event'    => ['nullable', 'string', 'max:100'],
            'from'     => ['required', 'date'],
            'to'       => ['required', 'date', 'after_or_equal:from'],
            'platform' => ['nullable', 'in:web,mobile,kiosk,admin'],
        ]);

        $query = AnalyticsEvent::query()
            ->whereBetween('occurred_at', [
                Carbon::parse($request->from)->startOfDay(),
                Carbon::parse($request->to)->endOfDay(),
            ]);

        if ($request->filled('event')) {
            $query->where('event_name', $request->event);
        }
        if ($request->filled('platform')) {
            $query->where('platform', $request->platform);
        }

        $filename = sprintf(
            'analytics_%s_%s_%s.csv',
            $request->event ?? 'all',
            $request->from,
            $request->to,
        );

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Event', 'Platform', 'Customer ID', 'Store ID', 'Session ID', 'Properties', 'Occurred At']);

            $query->chunk(500, function ($events) use ($handle) {
                foreach ($events as $ev) {
                    fputcsv($handle, [
                        $ev->id,
                        $ev->event_name,
                        $ev->platform,
                        $ev->customer_id,
                        $ev->store_id,
                        $ev->session_id,
                        json_encode($ev->properties),
                        $ev->occurred_at->toIso8601String(),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * GET /api/admin/analytics/summary?from=&to=
     */
    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
        ]);

        $from = Carbon::parse($request->from)->startOfDay();
        $to   = Carbon::parse($request->to)->endOfDay();

        $counts = AnalyticsEvent::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->selectRaw('event_name, platform, COUNT(*) as count')
            ->groupBy('event_name', 'platform')
            ->get()
            ->groupBy('event_name')
            ->map(fn ($rows) => [
                'total'      => $rows->sum('count'),
                'by_platform'=> $rows->pluck('count', 'platform')->all(),
            ]);

        return apiSuccess([
            'from'    => $request->from,
            'to'      => $request->to,
            'summary' => $counts,
        ]);
    }
}
