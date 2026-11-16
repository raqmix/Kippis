<?php

namespace App\Services\Loyalty;

use App\Core\Models\Order;
use App\Core\Models\PointsMultiplierRule;
use App\Core\Models\Setting;
use App\Services\AppOffer\AppOffer;

/**
 * How many loyalty points a completed order earns.
 *
 * Base: with `loyalty.egp_per_point` set (> 0) floor(total / egp_per_point);
 * otherwise the legacy round(total * loyalty.points_per_order_egp).
 * Multiplier: only orders on an app platform (app_offer.platforms) get the
 * highest matching PointsMultiplierRule; everything else earns x1.
 */
class PointsEarning
{
    public function __construct(private AppOffer $appOffer) {}

    public function egpPerPoint(): ?float
    {
        $value = Setting::get('loyalty.egp_per_point');

        return is_numeric($value) && (float) $value > 0 ? (float) $value : null;
    }

    public function basePoints(Order $order): int
    {
        $total = (float) $order->total;
        $egpPerPoint = $this->egpPerPoint();
        if ($egpPerPoint !== null) {
            return (int) floor($total / $egpPerPoint + 1e-9);
        }

        $pointsPerEgp = (int) Setting::get(
            'loyalty.points_per_order_egp',
            (int) config('core.loyalty.points_per_order_egp', 1),
        );

        return (int) round($total * $pointsPerEgp);
    }

    public function multiplierFor(Order $order): ?PointsMultiplierRule
    {
        if (! $this->appOffer->isAppPlatform($order->platform)) {
            return null;
        }

        $at = $order->created_at ?? now();

        return PointsMultiplierRule::query()->active()->get()
            ->filter(fn (PointsMultiplierRule $rule) => $rule->matches($at, $order->platform))
            ->sortByDesc(fn (PointsMultiplierRule $rule) => (float) $rule->multiplier)
            ->first();
    }

    /**
     * @return array{points:int, base:int, multiplier:float, rule:?PointsMultiplierRule}
     */
    public function calculate(Order $order): array
    {
        $base = $this->basePoints($order);
        $rule = $this->multiplierFor($order);
        $multiplier = $rule && (float) $rule->multiplier > 1 ? (float) $rule->multiplier : 1.0;
        $rule = $multiplier > 1 ? $rule : null;

        return [
            'points'     => (int) floor($base * $multiplier + 1e-9),
            'base'       => $base,
            'multiplier' => $multiplier,
            'rule'       => $rule,
        ];
    }
}
