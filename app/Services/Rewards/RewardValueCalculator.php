<?php

namespace App\Services\Rewards;

use App\Core\Models\Cart;
use App\Core\Models\CustomerRedeemWallet;

/**
 * EGP value a usable wallet entry takes off a cart, before the cart
 * caps it at its subtotal.
 *
 *   product    linked product's base price (unchanged legacy behaviour)
 *   free_item  unit price (incl. its add-ons) of the most expensive cart line
 *              whose product is in reward_config.category_ids, capped at
 *              reward_config.max_value; one unit only
 *   counter    0 (redeemed by the cashier, never in the app cart)
 */
class RewardValueCalculator
{
    public function valueFor(CustomerRedeemWallet $entry, Cart $cart): float
    {
        if (! $entry->isUsable()) {
            return 0.0;
        }

        return match ($entry->reward_type ?? CustomerRedeemWallet::TYPE_PRODUCT) {
            CustomerRedeemWallet::TYPE_PRODUCT   => $this->productValue($entry),
            CustomerRedeemWallet::TYPE_FREE_ITEM => $this->freeItemValue($entry, $cart),
            default => 0.0,
        };
    }

    /**
     * Id of the cart line whose one unit the gift covers (the card cup must
     * be a different unit), or null when the gift is not tied to a line.
     */
    public function coveredItemId(CustomerRedeemWallet $entry, Cart $cart): ?int
    {
        if (! $entry->isUsable()) {
            return null;
        }

        $config = $entry->reward_config ?? [];
        $type = $entry->reward_type ?? CustomerRedeemWallet::TYPE_PRODUCT;

        if ($type === CustomerRedeemWallet::TYPE_PRODUCT) {
            $productId = (int) ($entry->redeemItem?->product_id ?? ($config['product_id'] ?? 0));

            return $productId
                ? ($cart->items->first(fn ($i) => (int) $i->product_id === $productId && (int) $i->quantity >= 1)?->id)
                : null;
        }
        if ($type !== CustomerRedeemWallet::TYPE_FREE_ITEM) {
            return null;
        }

        $categoryIds = array_map('intval', (array) ($config['category_ids'] ?? []));
        $productIds = array_map('intval', (array) ($config['product_ids'] ?? []));
        $bestId = null;
        $best = 0.0;
        foreach ($cart->items as $item) {
            $product = $item->product;
            if (! $product || (int) $item->quantity < 1) {
                continue;
            }
            if (! in_array((int) $product->category_id, $categoryIds, true)
                && ! in_array((int) $product->id, $productIds, true)) {
                continue;
            }
            $unit = (float) $item->price ?: (float) $product->base_price;
            if ($unit > $best) {
                $best = $unit;
                $bestId = (int) $item->id;
            }
        }

        return $bestId;
    }

    private function productValue(CustomerRedeemWallet $entry): float
    {
        $product = $entry->redeemItem?->product;
        if (! $product && ! empty($entry->reward_config['product_id'])) {
            // Issued gifts (RewardIssuer) carry the product in reward_config.
            $product = \App\Core\Models\Product::find((int) $entry->reward_config['product_id']);
        }

        return $product ? (float) $product->base_price : 0.0;
    }

    private function freeItemValue(CustomerRedeemWallet $entry, Cart $cart): float
    {
        $config = $entry->reward_config ?? [];
        $categoryIds = array_map('intval', (array) ($config['category_ids'] ?? []));
        $productIds = array_map('intval', (array) ($config['product_ids'] ?? []));
        if (empty($categoryIds) && empty($productIds)) {
            return 0.0;
        }

        $best = 0.0;
        foreach ($cart->items as $item) {
            $product = $item->product;
            if (! $product || (int) $item->quantity < 1) {
                continue;
            }
            $matches = in_array((int) $product->category_id, $categoryIds, true)
                || in_array((int) $product->id, $productIds, true);
            if (! $matches) {
                continue;
            }
            $unit = (float) $item->price;
            if ($unit === 0.0) {
                $unit = (float) $product->base_price;
            }
            $best = max($best, $unit);
        }

        $cap = isset($config['max_value']) ? (float) $config['max_value'] : 0.0;
        if ($cap > 0) {
            $best = min($best, $cap);
        }

        return round($best, 2);
    }
}
