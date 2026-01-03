<?php

namespace App\Services;

use App\Core\Models\Cart;
use App\Core\Models\Product;
use App\Core\Repositories\CartRepository;
use App\Services\MixPriceCalculator;

class CartService
{
    public function __construct(
        private CartRepository $cartRepository,
        private MixPriceCalculator $mixPriceCalculator
    ) {
    }

    /**
     * Add a product to cart (with optional addons).
     *
     * @param Cart $cart
     * @param Product $product
     * @param int $quantity
     * @param array $addons Array of addon configurations: [{modifier_id, level}]
     * @return \App\Core\Models\CartItem
     * @throws \InvalidArgumentException
     */
    public function addProductToCart(Cart $cart, Product $product, int $quantity, array $addons = []): \App\Core\Models\CartItem
    {
        if (!$product->is_active) {
            throw new \InvalidArgumentException('Product is not active.');
        }

        // Calculate price with addons
        $priceResult = $this->mixPriceCalculator->calculateProductWithAddons($product, $addons);

        // Prepare configuration snapshot
        $configuration = null;
        if (!empty($addons)) {
            $configuration = [
                'addons' => $addons,
            ];
        }

        $payload = [
            'product_id' => $product->id,
            'quantity' => $quantity,
            'price' => $priceResult['total'],
            'item_type' => 'product',
            'ref_id' => $product->id,
            'name' => $product->getName(app()->getLocale()),
            'configuration' => $configuration,
        ];

        return $this->cartRepository->addItemUnified($cart, $payload);
    }

    /**
     * Add a mix or creator mix to cart.
     *
     * @param Cart $cart
     * @param string $itemType 'mix' or 'creator_mix'
     * @param array $configuration Mix configuration snapshot
     * @param int $quantity
     * @param int|null $refId Optional reference ID (mix_builder_id or creator_mix_id)
     * @param string|null $name Optional custom name
     * @return \App\Core\Models\CartItem
     * @throws \InvalidArgumentException
     */
    public function addMixToCart(
        Cart $cart,
        string $itemType,
        array $configuration,
        int $quantity = 1,
        ?int $refId = null,
        ?string $name = null
    ): \App\Core\Models\CartItem {
        if (!in_array($itemType, ['mix', 'creator_mix'])) {
            throw new \InvalidArgumentException("Invalid item type: {$itemType}");
        }

        // Calculate price
        $priceResult = $this->mixPriceCalculator->calculate($configuration);

        // Generate default name if not provided
        if (!$name) {
            $name = $itemType === 'creator_mix' ? 'Creator Mix' : 'Custom Mix';
        }

        $payload = [
            'quantity' => $quantity,
            'price' => $priceResult['total'],
            'item_type' => $itemType,
            'ref_id' => $refId,
            'name' => $name,
            'configuration' => $configuration,
        ];

        return $this->cartRepository->addItemUnified($cart, $payload);
    }
}

