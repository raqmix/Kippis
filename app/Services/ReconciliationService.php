<?php

namespace App\Services;

use App\Core\Models\PaymentTransaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ReconciliationService
{
    /**
     * Generate a reconciliation report for the given date range and optional gateway filter.
     */
    public function generateReport(Carbon $from, Carbon $to, ?string $gateway = null): array
    {
        $query = PaymentTransaction::query()
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->with('order:id,status,refund_status');

        if ($gateway) {
            $query->where('gateway', $gateway);
        }

        $transactions = $query->get();

        $byGateway = $transactions->groupBy('gateway')->map(function (Collection $group, string $gw) {
            $captures = $group->where('type', 'capture');
            $refunds  = $group->where('type', 'refund');
            $voids    = $group->where('type', 'void');

            return [
                'gateway'        => $gw,
                'capture_count'  => $captures->count(),
                'capture_total'  => $captures->sum('amount'),
                'refund_count'   => $refunds->count(),
                'refund_total'   => $refunds->sum('amount'),
                'void_count'     => $voids->count(),
                'void_total'     => $voids->sum('amount'),
                'net'            => $captures->sum('amount') - $refunds->sum('amount') - $voids->sum('amount'),
            ];
        })->values();

        $discrepancies = $this->findDiscrepancies($transactions);

        return [
            'from'          => $from->toDateString(),
            'to'            => $to->toDateString(),
            'gateway_filter'=> $gateway,
            'summary'       => $byGateway,
            'totals'        => [
                'captures'  => $transactions->where('type', 'capture')->sum('amount'),
                'refunds'   => $transactions->where('type', 'refund')->sum('amount'),
                'voids'     => $transactions->where('type', 'void')->sum('amount'),
                'net'       => $transactions->where('type', 'capture')->sum('amount')
                             - $transactions->where('type', 'refund')->sum('amount')
                             - $transactions->where('type', 'void')->sum('amount'),
            ],
            'transaction_count' => $transactions->count(),
            'discrepancies' => $discrepancies,
            'transactions'  => $transactions->map(fn ($t) => [
                'id'                => $t->id,
                'order_id'          => $t->order_id,
                'type'              => $t->type,
                'amount'            => $t->amount,
                'gateway'           => $t->gateway,
                'gateway_reference' => $t->gateway_reference,
                'gateway_status'    => $t->gateway_status,
                'reconciled'        => $t->reconciled,
                'reconciled_at'     => $t->reconciled_at?->toIso8601String(),
                'created_at'        => $t->created_at->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * Mark a set of transactions as reconciled.
     */
    public function markReconciled(array $transactionIds): int
    {
        return PaymentTransaction::whereIn('id', $transactionIds)
            ->update(['reconciled' => true, 'reconciled_at' => now()]);
    }

    /**
     * Identify discrepancies in the transaction set.
     */
    private function findDiscrepancies(Collection $transactions): array
    {
        $issues = [];

        // Duplicate gateway references
        $duplicates = $transactions
            ->filter(fn ($t) => $t->gateway_reference !== null)
            ->groupBy('gateway_reference')
            ->filter(fn (Collection $group) => $group->count() > 1);

        foreach ($duplicates as $ref => $group) {
            $issues[] = [
                'type'    => 'duplicate_reference',
                'message' => "Gateway reference '{$ref}' appears {$group->count()} times.",
                'ids'     => $group->pluck('id')->all(),
            ];
        }

        // Refund records without a matching capture transaction
        $captureOrderIds = $transactions->where('type', 'capture')->pluck('order_id')->unique();
        $orphanRefunds   = $transactions->whereIn('type', ['refund', 'void'])
            ->filter(fn ($t) => ! $captureOrderIds->contains($t->order_id));

        foreach ($orphanRefunds as $tx) {
            $issues[] = [
                'type'    => 'orphan_refund',
                'message' => "Refund/void transaction #{$tx->id} for order #{$tx->order_id} has no matching capture in this period.",
                'ids'     => [$tx->id],
            ];
        }

        return $issues;
    }
}
