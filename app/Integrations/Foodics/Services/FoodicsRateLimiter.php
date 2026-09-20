<?php

namespace App\Integrations\Foodics\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Process-wide spend gate for the Foodics API.
 *
 * Foodics allows 90 requests per minute per access token per IP. Every
 * caller in this app shares one token and one egress IP, so the budget is
 * global: the scheduled catalog sync, the order-status poll, queued order
 * pushes and any admin-triggered sync all draw on the same 90.
 *
 * We deliberately spend well under the ceiling (see MAX_PER_WINDOW). The
 * headroom absorbs bursts we cannot see coming — a queue worker picking up
 * a backlog, an operator clicking "Sync now" while the scheduler runs —
 * and being throttled locally costs a wait, whereas being throttled by
 * Foodics costs a rejected request that still counts against the budget.
 *
 * Counting is per wall-clock minute rather than a sliding window: it
 * matches how the upstream limit resets, and it keeps the cache key cheap
 * (one counter per minute, self-expiring).
 */
class FoodicsRateLimiter
{
    /**
     * Requests we allow ourselves per minute, against an upstream ceiling
     * of 90. The gap is deliberate headroom — see the class docblock.
     */
    private const MAX_PER_WINDOW = 60;

    /** Longest we will block a caller waiting for the next window. */
    private const MAX_WAIT_SECONDS = 65;

    /**
     * Block until there is budget for one request, then consume it.
     *
     * Returns the number of seconds spent waiting, so callers can log the
     * cost. Waits no longer than one window: if the budget is still gone
     * after that, we let the caller proceed rather than stall a queue
     * worker indefinitely — the client's 429 handling is the next line of
     * defence.
     */
    public function acquire(string $context = 'unknown'): int
    {
        $waited = 0;

        while ($waited < self::MAX_WAIT_SECONDS) {
            if ($this->tryConsume()) {
                return $waited;
            }

            $sleep = max(1, $this->secondsUntilNextWindow());
            sleep($sleep);
            $waited += $sleep;
        }

        Log::warning('FOODICS_THROTTLE_WAIT_EXCEEDED', [
            'context' => $context,
            'waited_seconds' => $waited,
        ]);

        return $waited;
    }

    /**
     * Consume one unit of budget if any remains.
     *
     * Cache::increment() on an existing key is atomic, but creating the
     * key is not, so we seed it with add() first — add() is atomic and
     * only one racing caller wins it. The TTL is two windows so a counter
     * created at :59.9 cannot expire before its own minute is over.
     */
    private function tryConsume(): bool
    {
        $key = $this->windowKey();

        Cache::add($key, 0, 120);

        $used = Cache::increment($key);

        // A driver that lost the key between add() and increment() returns
        // false or null. Treat that as "no budget information" and allow
        // the call rather than deadlocking every Foodics request.
        if (! is_int($used)) {
            return true;
        }

        return $used <= self::MAX_PER_WINDOW;
    }

    private function windowKey(): string
    {
        return 'foodics:ratelimit:' . floor(time() / 60);
    }

    private function secondsUntilNextWindow(): int
    {
        return 60 - (time() % 60);
    }

    /**
     * Drop the remaining budget for the current window.
     *
     * Called when Foodics tells us we are over the limit: our local count
     * evidently disagrees with theirs (another process, another host, or
     * traffic we do not mediate), so we stop spending until the window
     * turns over rather than trusting the counter.
     */
    public function exhaustCurrentWindow(): void
    {
        Cache::put($this->windowKey(), self::MAX_PER_WINDOW + 1, 120);
    }
}
