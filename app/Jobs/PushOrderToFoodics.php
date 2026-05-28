<?php

namespace App\Jobs;

use App\Core\Models\Customer;
use App\Core\Models\FoodicsModifierOption;
use App\Core\Models\Order;
use App\Core\Models\Product;
use App\Integrations\Foodics\Exceptions\FoodicsException;
use App\Integrations\Foodics\Exceptions\FoodicsForbiddenException;
use App\Integrations\Foodics\Exceptions\FoodicsNotFoundException;
use App\Integrations\Foodics\Exceptions\FoodicsUnauthorizedException;
use App\Integrations\Foodics\Exceptions\FoodicsValidationException;
use App\Integrations\Foodics\Services\FoodicsClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Pushes a freshly-created Kippis order to Foodics POS.
 *
 * Triggered by [[push-order-to-foodics-listener]] in response to OrderCreated.
 * Idempotent — exits early if already pushed. Skipped entirely for kiosk/cash
 * orders (pos_code set) and for stores not yet mapped to a Foodics branch.
 */
class PushOrderToFoodics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Retry up to 5 times across ~45 minutes for transient failures. */
    public int $tries = 5;

    public function __construct(public int $orderId)
    {
    }

    /** Exponential-ish backoff in seconds. */
    public function backoff(): array
    {
        return [30, 60, 180, 600, 1800];
    }

    public function handle(FoodicsClient $client): void
    {
        $order = Order::with(['store', 'customer'])->find($this->orderId);

        if (! $order) {
            Log::warning('FOODICS_PUSH_SKIPPED_MISSING_ORDER', ['order_id' => $this->orderId]);
            return;
        }

        if ($order->pos_code !== null) {
            Log::info('FOODICS_PUSH_SKIPPED_POS_CASH', [
                'order_id' => $order->id,
                'pos_code' => $order->pos_code,
            ]);
            return;
        }

        if ($order->foodics_order_id !== null) {
            Log::info('FOODICS_PUSH_SKIPPED_ALREADY_PUSHED', [
                'order_id' => $order->id,
                'foodics_order_id' => $order->foodics_order_id,
            ]);
            return;
        }

        $branchId = $order->store?->foodics_branch_id;
        if (! $branchId) {
            Log::warning('FOODICS_PUSH_SKIPPED_NO_BRANCH', [
                'order_id' => $order->id,
                'store_id' => $order->store_id,
            ]);
            return;
        }

        // Lazy customer sync: create a Foodics customer record on first push
        // for this customer so subsequent orders attach to the same profile.
        // Non-fatal: if Foodics rejects, the order still pushes without it.
        if ($order->customer && ! $order->customer->foodics_customer_id) {
            $this->lazyCreateFoodicsCustomer($client, $order->customer);
            $order->refresh();
        }

        $payload = $this->mapToFoodicsPayload($order, $branchId);

        if (empty($payload['products'])) {
            Log::warning('FOODICS_PUSH_SKIPPED_NO_MAPPABLE_PRODUCTS', [
                'order_id' => $order->id,
            ]);
            return;
        }

        $endpoint = config('foodics.endpoints.orders', 'v5/orders');

        try {
            $response = $client->post($endpoint, $payload);
        } catch (FoodicsValidationException $e) {
            // Permanent: bad payload. Don't retry; surface in failed_jobs only
            // after exhausting tries would be wasteful — fail fast.
            Log::error('FOODICS_PUSH_VALIDATION_FAILED', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
                'errors' => $e->errors,
                'payload' => $payload,
            ]);
            $this->fail($e);
            return;
        } catch (FoodicsUnauthorizedException | FoodicsForbiddenException | FoodicsNotFoundException $e) {
            // Permanent: bad token or wrong endpoint. Don't retry.
            Log::error('FOODICS_PUSH_AUTH_OR_ROUTE_FAILED', [
                'order_id' => $order->id,
                'error_class' => $e::class,
                'message' => $e->getMessage(),
            ]);
            $this->fail($e);
            return;
        }
        // Connection/timeout/rate-limit/5xx exceptions propagate naturally and
        // trigger queue retry per $tries + backoff().

        if (! $response->ok || ! is_array($response->data)) {
            Log::error('FOODICS_PUSH_FAILED_RESPONSE', [
                'order_id' => $order->id,
                'status' => $response->status_code,
                'error' => $response->error,
            ]);
            // Treat as transient — let the queue retry.
            throw new \RuntimeException(
                'Foodics order push returned non-OK response (status ' . $response->status_code . ')'
            );
        }

        $foodicsOrderId = $this->extractFoodicsOrderId($response->data);
        $foodicsReference = $this->extractFoodicsReference($response->data);

        $order->update([
            'foodics_order_id' => $foodicsOrderId,
            'foodics_reference' => $foodicsReference,
            'foodics_pushed_at' => now(),
        ]);

        Log::info('FOODICS_PUSH_SUCCESS', [
            'order_id' => $order->id,
            'foodics_order_id' => $foodicsOrderId,
            'foodics_reference' => $foodicsReference,
        ]);
    }

    /**
     * Final-failure hook — called after $tries are exhausted.
     */
    public function failed(\Throwable $e): void
    {
        Log::error('FOODICS_PUSH_FAILED', [
            'order_id' => $this->orderId,
            'message' => $e->getMessage(),
        ]);
    }

    /**
     * Build the JSON body Foodics expects for POST /v5/orders.
     * Field names follow Foodics v5 documented conventions; the single
     * mapping site keeps it easy to adjust when the API surface drifts.
     */
    private function mapToFoodicsPayload(Order $order, string $branchId): array
    {
        $items = is_array($order->items_snapshot) ? $order->items_snapshot : [];

        $foodicsProductIds = $this->resolveProductFoodicsIds($items);
        $foodicsOptionIds = $this->resolveFoodicsOptionIds($items);

        $products = [];
        foreach ($items as $line) {
            $kippisProductId = $line['product_id'] ?? null;
            if (! $kippisProductId || ! isset($foodicsProductIds[$kippisProductId])) {
                Log::warning('FOODICS_PUSH_UNMAPPED_PRODUCT', [
                    'order_id' => $order->id,
                    'kippis_product_id' => $kippisProductId,
                ]);
                continue;
            }

            $products[] = [
                'product_id' => $foodicsProductIds[$kippisProductId],
                'quantity' => (int) ($line['quantity'] ?? 1),
                'unit_price' => (float) ($line['price'] ?? 0),
                'modifiers' => $this->buildLineModifiers($order, $line, $foodicsOptionIds),
                'notes' => $line['note'] ?? null,
            ];
        }

        // Foodics auto-assigns its own numeric `reference` (sequence per branch)
        // and ignores `reference_x` / `number` on create — only `kitchen_notes`
        // surfaces our pickup code to staff on the ticket.
        $payload = [
            'branch_id' => $branchId,
            'type' => 2, // 2 = Takeaway in Foodics v5 order type enum (1=DineIn,2=Takeaway,3=Delivery,4=DriveThru)
            'kitchen_notes' => $order->pickup_code
                ? 'Pickup #' . $order->pickup_code
                : 'Kippis #' . $order->id,
            'products' => $products,
            'discount_amount' => (float) ($order->discount ?? 0),
            'total' => (float) $order->total,
        ];

        if ($order->customer) {
            if ($order->customer->foodics_customer_id) {
                $payload['customer_id'] = $order->customer->foodics_customer_id;
            } else {
                // Fallback when lazy sync didn't land a foodics_customer_id —
                // ship the contact info inline so the branch still has it.
                $payload['customer'] = [
                    'name' => $order->customer->name,
                    'email' => $order->customer->email,
                    'phone' => $order->customer->phone,
                ];
            }
        }

        return $payload;
    }

    /**
     * Create the customer on Foodics so we can reference them by id on future
     * orders. Best-effort: any failure is logged and ignored — the order push
     * still proceeds with inline customer info.
     */
    private function lazyCreateFoodicsCustomer(FoodicsClient $client, Customer $customer): void
    {
        $endpoint = config('foodics.endpoints.customers', 'v5/customers');

        try {
            $response = $client->post($endpoint, [
                'name'  => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
            ]);
        } catch (FoodicsException $e) {
            Log::warning('FOODICS_CUSTOMER_CREATE_FAILED', [
                'customer_id' => $customer->id,
                'error_class' => $e::class,
                'message' => $e->getMessage(),
            ]);
            return;
        }

        if (! $response->ok || ! is_array($response->data)) {
            Log::warning('FOODICS_CUSTOMER_CREATE_NON_OK', [
                'customer_id' => $customer->id,
                'status' => $response->status_code,
            ]);
            return;
        }

        $data = is_array($response->data['data'] ?? null) ? $response->data['data'] : $response->data;
        $foodicsCustomerId = $data['id'] ?? null;

        if ($foodicsCustomerId !== null) {
            $customer->update(['foodics_customer_id' => (string) $foodicsCustomerId]);
            Log::info('FOODICS_CUSTOMER_CREATED', [
                'customer_id' => $customer->id,
                'foodics_customer_id' => $foodicsCustomerId,
            ]);
        }
    }

    /**
     * Bulk-resolve Kippis product ids → Foodics product ids.
     * Returns [kippis_id => foodics_id] for products that have a foodics_id set.
     */
    private function resolveProductFoodicsIds(array $items): array
    {
        $kippisIds = collect($items)
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($kippisIds)) {
            return [];
        }

        return Product::query()
            ->whereIn('id', $kippisIds)
            ->whereNotNull('foodics_id')
            ->pluck('foodics_id', 'id')
            ->all();
    }

    /**
     * Bulk-resolve Kippis modifier-option ids → Foodics option ids.
     * Selected options for both customized products and built mixes live in
     * each line's configuration.foodics_option_ids as Kippis option ids.
     * Returns [kippis_option_id => foodics_id].
     */
    private function resolveFoodicsOptionIds(array $items): array
    {
        $ids = [];
        foreach ($items as $line) {
            $cfg = $line['configuration'] ?? null;
            if (is_array($cfg) && ! empty($cfg['foodics_option_ids']) && is_array($cfg['foodics_option_ids'])) {
                foreach ($cfg['foodics_option_ids'] as $id) {
                    $ids[] = (int) $id;
                }
            }
        }

        if (empty($ids)) {
            return [];
        }

        return FoodicsModifierOption::query()
            ->whereIn('id', array_values(array_unique($ids)))
            ->whereNotNull('foodics_id')
            ->pluck('foodics_id', 'id')
            ->all();
    }

    /**
     * Build the Foodics modifiers array for one order line. Combines any legacy
     * `modifiers` snapshot shape with the selected Foodics options stored in
     * `configuration.foodics_option_ids` (translated to Foodics option ids).
     */
    private function buildLineModifiers(Order $order, array $line, array $foodicsOptionIds): array
    {
        $modifiers = $this->mapModifiers($line['modifiers'] ?? null);

        $cfg = $line['configuration'] ?? null;
        if (is_array($cfg) && ! empty($cfg['foodics_option_ids']) && is_array($cfg['foodics_option_ids'])) {
            foreach ($cfg['foodics_option_ids'] as $kippisOptionId) {
                $foodicsOptionId = $foodicsOptionIds[(int) $kippisOptionId] ?? null;
                if ($foodicsOptionId !== null) {
                    $modifiers[] = ['option_id' => $foodicsOptionId];
                } else {
                    Log::warning('FOODICS_PUSH_UNMAPPED_OPTION', [
                        'order_id' => $order->id,
                        'kippis_option_id' => $kippisOptionId,
                    ]);
                }
            }
        }

        return $modifiers;
    }

    /**
     * Snapshot modifiers come in various shapes (per cart item). Best-effort
     * pass the option ids through; Foodics ignores unknown fields per v5 docs.
     */
    private function mapModifiers(mixed $modifiers): array
    {
        if (! is_array($modifiers)) {
            return [];
        }

        $out = [];
        foreach ($modifiers as $modifier) {
            if (! is_array($modifier)) {
                continue;
            }
            $options = $modifier['options'] ?? $modifier['selected'] ?? [];
            if (! is_array($options)) {
                continue;
            }
            foreach ($options as $opt) {
                $foodicsOptionId = is_array($opt)
                    ? ($opt['foodics_id'] ?? $opt['id'] ?? null)
                    : $opt;
                if ($foodicsOptionId !== null) {
                    $out[] = ['option_id' => $foodicsOptionId];
                }
            }
        }
        return $out;
    }

    /**
     * Foodics v5 wraps single-resource responses as {data: {id: ...}}.
     * Tolerate either nested or flat for safety.
     */
    private function extractFoodicsOrderId(array $data): ?string
    {
        if (isset($data['data']) && is_array($data['data']) && isset($data['data']['id'])) {
            return (string) $data['data']['id'];
        }
        return isset($data['id']) ? (string) $data['id'] : null;
    }

    /**
     * Foodics auto-assigns a per-branch numeric `reference` on order create —
     * the human-readable number staff see on tickets.
     */
    private function extractFoodicsReference(array $data): ?string
    {
        $body = (isset($data['data']) && is_array($data['data'])) ? $data['data'] : $data;
        return isset($body['reference']) ? (string) $body['reference'] : null;
    }
}
