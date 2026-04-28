<?php

namespace App\Console\Commands;

use App\Core\Models\ActivityLog;
use App\Core\Models\AnalyticsEvent;
use App\Core\Models\Customer;
use App\Core\Models\Order;
use App\Core\Models\SecurityLog;
use App\Core\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PruneDataCommand extends Command
{
    protected $signature   = 'data:prune {--dry-run : Show what would be deleted without actually deleting}';
    protected $description = 'Prune old analytics events, anonymize archived orders, and hard-delete stale soft-deleted customers';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        if ($dry) {
            $this->warn('DRY RUN — no data will be modified.');
        }

        $summary = [
            'analytics_events_deleted'   => 0,
            'orders_anonymized'          => 0,
            'customers_hard_deleted'     => 0,
            'security_logs_deleted'      => 0,
            'activity_logs_deleted'      => 0,
        ];

        // -----------------------------------------------------------------------
        // 1. Prune analytics_events
        // -----------------------------------------------------------------------
        $analyticsAgeDays = (int) Setting::get('data_retention.analytics_events_days', 365);
        $analyticsCutoff  = Carbon::now()->subDays($analyticsAgeDays);

        $analyticsCount = AnalyticsEvent::query()
            ->where('created_at', '<', $analyticsCutoff)
            ->count();

        if (! $dry && $analyticsCount > 0) {
            AnalyticsEvent::query()
                ->where('created_at', '<', $analyticsCutoff)
                ->delete();
        }

        $summary['analytics_events_deleted'] = $analyticsCount;
        $this->line("Analytics events older than {$analyticsAgeDays} days: {$analyticsCount}");

        // -----------------------------------------------------------------------
        // 2. Anonymize old orders (keep financials, redact PII in snapshot)
        // -----------------------------------------------------------------------
        $orderHistoryYears = (int) Setting::get('data_retention.order_history_years', 5);
        $orderCutoff       = Carbon::now()->subYears($orderHistoryYears);

        $oldOrders = Order::query()
            ->where('created_at', '<', $orderCutoff)
            ->whereNull('anonymized_at')
            ->get(['id', 'items_snapshot', 'modifiers_snapshot']);

        if (! $dry) {
            foreach ($oldOrders as $order) {
                // Strip any PII from snapshots (customer names in notes, etc.)
                $snapshot = $order->items_snapshot;
                if (is_array($snapshot)) {
                    array_walk_recursive($snapshot, function (&$value, $key): void {
                        if (in_array($key, ['customer_note', 'customer_name', 'phone', 'email'], true)) {
                            $value = null;
                        }
                    });
                }

                DB::table('orders')->where('id', $order->id)->update([
                    'customer_id'        => null,
                    'items_snapshot'     => json_encode($snapshot),
                    'modifiers_snapshot' => null,
                    'anonymized_at'      => now(),
                ]);
            }
        }

        $summary['orders_anonymized'] = $oldOrders->count();
        $this->line("Orders older than {$orderHistoryYears} years to anonymize: {$oldOrders->count()}");

        // -----------------------------------------------------------------------
        // 3. Hard-delete soft-deleted customers past retention window
        // -----------------------------------------------------------------------
        $softDeletedDays = (int) Setting::get('data_retention.soft_deleted_customers_days', 90);
        $customerCutoff  = Carbon::now()->subDays($softDeletedDays);

        $expiredCustomers = Customer::onlyTrashed()
            ->where('deleted_at', '<', $customerCutoff)
            ->get(['id']);

        if (! $dry && $expiredCustomers->isNotEmpty()) {
            $ids = $expiredCustomers->pluck('id');

            // Cascade: remove loyalty, referrals, check-ins, carts
            DB::table('loyalty_wallets')->whereIn('customer_id', $ids)->delete();
            DB::table('loyalty_transactions')->whereIn('customer_id', $ids)->delete();
            DB::table('referrals')->whereIn('inviter_id', $ids)->orWhereIn('invitee_id', $ids)->delete();
            DB::table('check_ins')->whereIn('customer_id', $ids)->delete();
            DB::table('cart_items')
                ->whereIn('cart_id', DB::table('carts')->whereIn('customer_id', $ids)->pluck('id'))
                ->delete();
            DB::table('carts')->whereIn('customer_id', $ids)->delete();

            Customer::onlyTrashed()->whereIn('id', $ids)->forceDelete();
        }

        $summary['customers_hard_deleted'] = $expiredCustomers->count();
        $this->line("Soft-deleted customers older than {$softDeletedDays} days: {$expiredCustomers->count()}");

        // -----------------------------------------------------------------------
        // 4. Prune security_logs
        // -----------------------------------------------------------------------
        $securityLogDays = (int) Setting::get('data_retention.security_logs_days', 180);
        $securityCutoff  = Carbon::now()->subDays($securityLogDays);

        $securityCount = SecurityLog::query()
            ->where('created_at', '<', $securityCutoff)
            ->count();

        if (! $dry && $securityCount > 0) {
            SecurityLog::query()
                ->where('created_at', '<', $securityCutoff)
                ->delete();
        }

        $summary['security_logs_deleted'] = $securityCount;
        $this->line("Security logs older than {$securityLogDays} days: {$securityCount}");

        // -----------------------------------------------------------------------
        // 5. Prune activity_logs (session/admin audit trail)
        // -----------------------------------------------------------------------
        $sessionLogDays = (int) Setting::get('data_retention.session_logs_days', 30);
        $sessionCutoff  = Carbon::now()->subDays($sessionLogDays);

        $activityCount = ActivityLog::query()
            ->where('created_at', '<', $sessionCutoff)
            ->count();

        if (! $dry && $activityCount > 0) {
            ActivityLog::query()
                ->where('created_at', '<', $sessionCutoff)
                ->delete();
        }

        $summary['activity_logs_deleted'] = $activityCount;
        $this->line("Activity logs older than {$sessionLogDays} days: {$activityCount}");

        // -----------------------------------------------------------------------
        // 6. Audit log entry
        // -----------------------------------------------------------------------
        if (! $dry) {
            Log::channel('single')->info('data:prune completed', $summary);
        }

        $this->newLine();
        $this->info($dry ? 'Dry run complete — no changes made.' : 'Data pruning complete.');
        $this->table(
            ['Metric', 'Count'],
            collect($summary)->map(fn ($v, $k) => [str_replace('_', ' ', $k), $v])->values()->all()
        );

        return self::SUCCESS;
    }
}
