<?php

namespace App\Console\Commands;

use App\Core\Models\Order;
use App\Integrations\Foodics\Services\FoodicsClient;
use App\Integrations\Foodics\Support\FoodicsStatusMapper;
use Illuminate\Console\Command;

/**
 * Print what Foodics actually holds for an order next to what Kippis holds.
 *
 * Two open questions need real data rather than a reading of our own code:
 *
 *  - Money: whether the check reconciles as lines - discount = paid, and
 *    which side (ours or theirs) carries a wrong number.
 *  - Lifecycle: what a branch "closing payment" does to the order. Our
 *    webhook and poll both key off `status` alone; if closing a payment
 *    moves some other field (is_paid, a payments[] entry, a closed/business
 *    date) and leaves `status` untouched, neither path can ever see it and
 *    the order stays active in the app forever.
 *
 * Read-only. Spends one Foodics request per order.
 */
class FoodicsInspectOrder extends Command
{
    protected $signature = 'foodics:inspect-order
        {order? : Kippis order id}
        {--foodics= : Foodics order id, if the Kippis row is unknown}
        {--stuck : Inspect the oldest non-terminal orders instead}
        {--limit=5 : How many to inspect with --stuck}
        {--raw : Dump the full Foodics payload}';

    protected $description = 'Compare a Kippis order against the live Foodics order to locate money and lifecycle mismatches.';

    public function handle(FoodicsClient $client): int
    {
        $endpoint = config('foodics.endpoints.orders', 'v5/orders');

        $orders = $this->targets();

        if ($orders->isEmpty()) {
            $this->error('No matching order. Pass a Kippis order id, --foodics=<id>, or --stuck.');
            return self::FAILURE;
        }

        foreach ($orders as $order) {
            $this->inspect($client, $endpoint, $order);
        }

        return self::SUCCESS;
    }

    /** @return \Illuminate\Support\Collection<int, Order> */
    private function targets()
    {
        if ($this->option('stuck')) {
            return Order::query()
                ->whereNotNull('foodics_order_id')
                ->whereNotIn('status', FoodicsStatusMapper::terminalStatuses())
                ->orderBy('created_at')
                ->limit((int) $this->option('limit'))
                ->get();
        }

        if ($foodicsId = $this->option('foodics')) {
            return Order::where('foodics_order_id', (string) $foodicsId)->get();
        }

        $id = $this->argument('order');

        return $id ? Order::whereKey($id)->get() : collect();
    }

    private function inspect(FoodicsClient $client, string $endpoint, Order $order): void
    {
        $this->line('');
        $this->info("=== Kippis order #{$order->id} ===");
        $this->line('  status        : ' . $order->status);
        $this->line('  subtotal      : ' . number_format((float) $order->subtotal, 2));
        $this->line('  discount      : ' . number_format((float) $order->discount, 2));
        $this->line('  total (paid)  : ' . number_format((float) $order->total, 2));
        $this->line('  foodics id    : ' . ($order->foodics_order_id ?: '—'));
        $this->line('  gateway id    : ' . ($order->gateway_order_id ?: '—'));
        $this->line('  created       : ' . $order->created_at);

        // What the current code would send to Foodics for this order. The
        // product lines carry undiscounted prices, so `total` must be the
        // pre-discount figure or the check cannot reconcile.
        $this->line('  we would send : total=' . number_format((float) $order->subtotal, 2)
            . ', discount_amount=' . number_format((float) $order->discount, 2)
            . ' → reconciles to ' . number_format((float) $order->subtotal - (float) $order->discount, 2));

        if (! $order->foodics_order_id) {
            $this->warn('  Never pushed to Foodics — nothing to compare.');
            return;
        }

        try {
            $response = $client->get("{$endpoint}/{$order->foodics_order_id}");
        } catch (\Throwable $e) {
            $this->error('  Foodics fetch failed: ' . $e->getMessage());
            return;
        }

        if (! $response->ok || ! is_array($response->data)) {
            $this->error('  Foodics returned HTTP ' . $response->status_code);
            return;
        }

        $data = is_array($response->data['data'] ?? null) ? $response->data['data'] : $response->data;

        $this->info('--- Foodics check ---');

        $rawStatus = $data['status'] ?? null;
        $mapped = FoodicsStatusMapper::fromFoodics($rawStatus);
        $this->line('  status        : ' . var_export($rawStatus, true) . ' → maps to ' . ($mapped ?? 'UNMAPPED'));

        foreach (['subtotal_price', 'discount_amount', 'total_price', 'rounding_amount', 'tax_amount'] as $field) {
            if (array_key_exists($field, $data)) {
                $this->line(str_pad("  {$field}", 18) . ': ' . var_export($data[$field], true));
            }
        }

        // The lifecycle question: which fields, other than `status`, move
        // when a branch closes payment. Whatever changes here is what the
        // webhook and poll must learn to read.
        $this->info('--- Lifecycle / payment fields ---');
        $lifecycle = ['is_paid', 'paid_at', 'closed_at', 'business_date', 'kitchen_done_at', 'status_history'];
        $sawAny = false;
        foreach ($lifecycle as $field) {
            if (array_key_exists($field, $data)) {
                $sawAny = true;
                $value = is_scalar($data[$field]) || $data[$field] === null
                    ? var_export($data[$field], true)
                    : json_encode($data[$field]);
                $this->line(str_pad("  {$field}", 18) . ': ' . $value);
            }
        }

        if (isset($data['payments']) && is_array($data['payments'])) {
            $sawAny = true;
            $this->line('  payments      : ' . count($data['payments']) . ' entry/entries');
            foreach ($data['payments'] as $i => $payment) {
                $amount = $payment['amount'] ?? '?';
                $method = $payment['payment_method']['name'] ?? ($payment['payment_method_id'] ?? '?');
                $this->line("     [{$i}] amount={$amount} method={$method}");
            }
        }

        if (! $sawAny) {
            $this->line('  (none of the expected payment/lifecycle fields are present)');
        }

        $this->line('  all keys      : ' . implode(', ', array_keys($data)));

        // Verdicts.
        $this->info('--- Verdict ---');

        $foodicsTotal = isset($data['total_price']) ? round((float) $data['total_price'], 2) : null;
        $kippisPaid = round((float) $order->total, 2);

        if ($foodicsTotal === null) {
            $this->warn('  Money: Foodics did not return total_price — cannot compare.');
        } elseif (abs($foodicsTotal - $kippisPaid) <= 0.01) {
            $this->line("  <fg=green>Money OK</> — Foodics total {$foodicsTotal} matches the {$kippisPaid} charged.");
        } else {
            $this->line("  <fg=red;options=bold>MONEY MISMATCH</> — Foodics says {$foodicsTotal}, customer paid {$kippisPaid}.");
        }

        if ($mapped === null) {
            $this->line('  <fg=red;options=bold>LIFECYCLE</> — Foodics status is unmapped, so no update can ever apply.');
        } elseif ($mapped === $order->status) {
            $this->line('  <fg=green>Lifecycle in sync</> — both sides agree on ' . $mapped . '.');
        } else {
            $this->line("  <fg=yellow>LIFECYCLE DRIFT</> — Foodics maps to '{$mapped}' but Kippis holds '{$order->status}'.");
            $this->line('     The next poll should correct this. If it does not, the poll is not reaching this order.');
        }

        // The specific failure mode behind "closed in Foodics, still active
        // in the app": the branch settled the check, but `status` never moved.
        $looksPaid = ($data['is_paid'] ?? false) || ! empty($data['payments']) || ! empty($data['closed_at']);
        if ($looksPaid && ! in_array($order->status, FoodicsStatusMapper::terminalStatuses(), true) && $mapped === $order->status) {
            $this->line('  <fg=red;options=bold>ROOT CAUSE CANDIDATE</> — the check is settled in Foodics, but `status`');
            $this->line('     still maps to a non-terminal Kippis state. Closing payment does not move `status`,');
            $this->line('     so neither the webhook nor the poll can ever complete this order.');
        }

        if ($this->option('raw')) {
            $this->info('--- Raw payload ---');
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }
}
