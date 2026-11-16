<?php

namespace App\Services\Loyalty;

use App\Core\Models\Order;
use App\Services\AppOffer\AppOffer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Monthly Big Win: every completed app order (platform in
 * app_offer.platforms, with a customer) placed in a Cairo calendar month
 * is one entry.
 */
class MonthlyDrawService
{
    public const CSV_HEADER = ['order_id', 'date', 'customer_id', 'name', 'phone', 'store'];

    public function __construct(private AppOffer $appOffer) {}

    /** @param string $month "YYYY-MM" */
    public function entries(string $month): Builder
    {
        $tz = config('core.business_timezone', 'Africa/Cairo');
        $start = CarbonImmutable::createFromFormat('!Y-m', $month, $tz)->startOfMonth();
        $end = $start->addMonth();

        return Order::query()
            ->with(['customer', 'store'])
            ->where('status', 'completed')
            ->whereNotNull('customer_id')
            ->whereIn('platform', $this->appOffer->platforms() ?: ['__none__'])
            ->where('created_at', '>=', $start->utc())
            ->where('created_at', '<', $end->utc())
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /** @return Collection<int, array<int, string|int>> */
    public function rows(string $month): Collection
    {
        $tz = config('core.business_timezone', 'Africa/Cairo');

        return $this->entries($month)->get()->map(function (Order $order) use ($tz) {
            $customer = $order->customer;
            $phone = $customer ? trim(($customer->country_code ?? '') . ' ' . ($customer->phone ?? '')) : '';

            return [
                $order->id,
                $order->created_at->copy()->setTimezone($tz)->format('Y-m-d H:i'),
                $order->customer_id,
                self::safe($customer?->name ?? ''),
                preg_match('/^\+?[\d\s\-()]*$/', $phone) ? $phone : self::safe($phone),
                self::safe($order->store?->name ?? ''),
            ];
        });
    }

    /** CSV text (UTF-8 with BOM so Excel reads Arabic names). */
    public function csv(string $month): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, self::CSV_HEADER);
        foreach ($this->rows($month) as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return "\xEF\xBB\xBF" . $csv;
    }

    /** A random entry (each order counts once, so more orders = more chances). */
    public function pickWinner(string $month): ?Order
    {
        return $this->entries($month)->inRandomOrder()->first();
    }

    /** Neutralise spreadsheet formulas in exported text. */
    private static function safe(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $value : $value;
    }
}
