<?php

namespace App\Services\Reports;

use App\Core\Models\Customer;
use App\Core\Models\Order;
use App\Core\Models\Store;
use App\Services\AppOffer\AppOffer;
use Illuminate\Support\Carbon;

/**
 * Weekly report (app brief item 18): per-store sign-ups, first orders,
 * app orders and all orders for one Monday-Sunday week in the business
 * timezone (Africa/Cairo).
 *
 * Definitions (existing figures only):
 *  - An "order" is a completed order (OrderStatus::COMPLETED); revenue is
 *    the sum of orders.total.
 *  - "App orders" are orders whose platform is one of the app platforms
 *    (Setting app_offer.platforms, default mobile).
 *  - A customer's "first order" is their earliest completed order ever;
 *    it counts for the week and store it was placed in.
 *  - "New sign-ups" are customers created in the week, attributed to the
 *    store of their first completed order (even if that was placed later),
 *    or to "No order yet" when they have none.
 */
class WeeklyStoreReport
{
    public const NO_ORDER_ROW = 'no_order';

    public function __construct(private AppOffer $appOffer)
    {
    }

    /** Monday 00:00 of the week containing $date, in the business timezone. */
    public static function weekStart(?string $date = null): Carbon
    {
        $tz = config('core.business_timezone', 'Africa/Cairo');

        return Carbon::parse($date ?: 'now', $tz)->startOfWeek(Carbon::MONDAY)->startOfDay();
    }

    /**
     * @return array{week_start: string, week_end: string, rows: array<int, array<string, mixed>>, totals: array<string, mixed>}
     */
    public function build(?string $date = null): array
    {
        $start = self::weekStart($date);
        $end = $start->copy()->addWeek();
        $from = $start->copy()->utc();
        $to = $end->copy()->utc();

        $rows = [];
        foreach (Store::orderBy('id')->get(['id', 'name']) as $store) {
            $rows[$store->id] = $this->emptyRow($store->id, (string) $store->name);
        }
        $rows[self::NO_ORDER_ROW] = $this->emptyRow(self::NO_ORDER_ROW, 'No order yet');

        $completed = fn () => Order::query()
            ->where('status', 'completed')
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to);

        // All orders / app orders per store.
        foreach ($completed()->selectRaw('store_id, count(*) as c, coalesce(sum(total), 0) as r')->groupBy('store_id')->get() as $r) {
            $this->ensureRow($rows, $r->store_id);
            $rows[$r->store_id]['all_orders'] = (int) $r->c;
            $rows[$r->store_id]['all_revenue'] = (float) $r->r;
        }
        $platforms = $this->appOffer->platforms();
        if ($platforms) {
            foreach ($completed()->whereIn('platform', $platforms)->selectRaw('store_id, count(*) as c, coalesce(sum(total), 0) as r')->groupBy('store_id')->get() as $r) {
                $this->ensureRow($rows, $r->store_id);
                $rows[$r->store_id]['app_orders'] = (int) $r->c;
                $rows[$r->store_id]['app_revenue'] = (float) $r->r;
            }
        }

        // First orders: no earlier completed order for the same customer.
        $firsts = $completed()
            ->whereNotNull('customer_id')
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('orders as prev')
                    ->whereColumn('prev.customer_id', 'orders.customer_id')
                    ->where('prev.status', 'completed')
                    ->whereNull('prev.deleted_at')
                    ->where(function ($w) {
                        $w->whereColumn('prev.created_at', '<', 'orders.created_at')
                            ->orWhere(function ($same) {
                                $same->whereColumn('prev.created_at', 'orders.created_at')
                                    ->whereColumn('prev.id', '<', 'orders.id');
                            });
                    });
            })
            ->selectRaw('store_id, count(*) as c')->groupBy('store_id')->get();
        foreach ($firsts as $r) {
            $this->ensureRow($rows, $r->store_id);
            $rows[$r->store_id]['first_orders'] = (int) $r->c;
        }

        // Sign-ups, attributed to the store of the customer's first completed order.
        $signups = Customer::query()
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to)
            ->selectSub(
                Order::query()->select('store_id')
                    ->whereColumn('orders.customer_id', 'customers.id')
                    ->where('status', 'completed')
                    ->orderBy('created_at')->orderBy('id')->limit(1),
                'first_store_id'
            )
            ->get(['id']);
        foreach ($signups as $customer) {
            $key = $customer->first_store_id ?? self::NO_ORDER_ROW;
            $this->ensureRow($rows, $key);
            $rows[$key]['signups']++;
        }

        $rows = array_values($rows);
        $keys = ['signups', 'first_orders', 'app_orders', 'app_revenue', 'all_orders', 'all_revenue'];
        $totals = array_fill_keys($keys, 0) + ['label' => 'Total'];
        foreach ($rows as $row) {
            foreach ($keys as $k) {
                $totals[$k] += $row[$k];
            }
        }

        return [
            'week_start' => $start->toDateString(),
            'week_end' => $end->copy()->subDay()->toDateString(),
            'rows' => $rows,
            'totals' => $totals,
        ];
    }

    /** @return resource-less CSV text (header + rows + totals) */
    public function toCsv(array $report): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Week (Mon-Sun, Cairo)', 'Store', 'New sign-ups', 'First orders', 'App orders', 'App revenue (EGP)', 'All orders', 'All revenue (EGP)']);
        $week = $report['week_start'] . ' to ' . $report['week_end'];
        foreach ([...$report['rows'], $report['totals']] as $row) {
            fputcsv($handle, [
                $week, $row['label'], $row['signups'], $row['first_orders'],
                $row['app_orders'], number_format($row['app_revenue'], 2, '.', ''),
                $row['all_orders'], number_format($row['all_revenue'], 2, '.', ''),
            ]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    private function emptyRow(int|string $id, string $label): array
    {
        return [
            'id' => $id, 'label' => $label,
            'signups' => 0, 'first_orders' => 0,
            'app_orders' => 0, 'app_revenue' => 0.0,
            'all_orders' => 0, 'all_revenue' => 0.0,
        ];
    }

    /** Orders may reference a store that was deleted since: keep them visible. */
    private function ensureRow(array &$rows, int|string|null $id): void
    {
        if ($id !== null && ! isset($rows[$id])) {
            $rows[$id] = $this->emptyRow($id, 'Store #' . $id);
        }
    }
}
