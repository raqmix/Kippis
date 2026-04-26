<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReconciliationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReconciliationController extends Controller
{
    public function __construct(private readonly ReconciliationService $reconciliation) {}

    /**
     * Generate reconciliation report.
     *
     * GET /api/admin/reconciliation?from=2026-04-01&to=2026-04-26&gateway=mastercard
     */
    public function report(Request $request): JsonResponse
    {
        $request->validate([
            'from'    => ['required', 'date'],
            'to'      => ['required', 'date', 'after_or_equal:from'],
            'gateway' => ['nullable', 'in:mastercard,apple_pay,cash,other'],
        ]);

        $report = $this->reconciliation->generateReport(
            Carbon::parse($request->from),
            Carbon::parse($request->to),
            $request->gateway,
        );

        return apiSuccess($report);
    }

    /**
     * Mark transactions as reconciled.
     *
     * POST /api/admin/reconciliation/mark-reconciled
     */
    public function markReconciled(Request $request): JsonResponse
    {
        $data = $request->validate([
            'transaction_ids'   => ['required', 'array', 'min:1'],
            'transaction_ids.*' => ['integer', 'min:1'],
        ]);

        $count = $this->reconciliation->markReconciled($data['transaction_ids']);

        return apiSuccess(['reconciled_count' => $count]);
    }

    /**
     * Export reconciliation data as CSV download.
     *
     * GET /api/admin/reconciliation/export?from=...&to=...&format=csv
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
        ]);

        $report  = $this->reconciliation->generateReport(
            Carbon::parse($request->from),
            Carbon::parse($request->to),
            $request->gateway,
        );

        $filename = "reconciliation_{$report['from']}_{$report['to']}.csv";

        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Order ID', 'Type', 'Amount (piasters)', 'Gateway', 'Reference', 'Status', 'Reconciled', 'Date']);

            foreach ($report['transactions'] as $tx) {
                fputcsv($handle, [
                    $tx['id'],
                    $tx['order_id'],
                    $tx['type'],
                    $tx['amount'],
                    $tx['gateway'],
                    $tx['gateway_reference'],
                    $tx['gateway_status'],
                    $tx['reconciled'] ? 'Yes' : 'No',
                    $tx['created_at'],
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
