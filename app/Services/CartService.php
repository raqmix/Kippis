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
     * @param string|null $note Optional note for the cart item
     * @return \App\Core\Models\CartItem
     * @throws \InvalidArgumentException
     */
    public function addProductToCart(Cart $cart, Product $product, int $quantity, array $addons = [], ?string $note = null, array $foodicsOptionIds = []): \App\Core\Models\CartItem
    {
        if (!$product->is_active) {
            throw new \InvalidArgumentException('Product is not active.');
        }

        // Calculate price with addons
        $priceResult = $this->mixPriceCalculator->calculateProductWithAddons($product, $addons);

        // Add Foodics modifier option prices
        $foodicsOptionsTotal = 0.0;
        if (!empty($foodicsOptionIds)) {
            $foodicsOptionsTotal = \App\Core\Models\FoodicsModifierOption::whereIn('id', $foodicsOptionIds)
                ->where('is_active', true)
                ->sum('price');
        }

        // Prepare configuration snapshot
        $configuration = null;
        if (!empty($addons) || !empty($foodicsOptionIds)) {
            $configuration = [
                'addons' => !empty($addons) ? $addons : null,
                'foodics_option_ids' => !empty($foodicsOptionIds) ? $foodicsOptionIds : null,
            ];
        }

        $payload = [
            'product_id' => $product->id,
            'quantity' => $quantity,
            'price' => round($priceResult['total'] + $foodicsOptionsTotal, 2),
            'item_type' => 'product',
            'ref_id' => $product->id,
            'name' => $product->getName(app()->getLocale()),
            'configuration' => $configuration,
            'note' => $note,
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
     * @param string|null $note Optional note for the cart item
     * @return \App\Core\Models\CartItem
     * @throws \InvalidArgumentException
     */
    public function addMixToCart(
        Cart $cart,
        string $itemType,
        array $configuration,
        int $quantity = 1,
        ?int $refId = null,
        ?string $name = null,
        ?string $note = null
    ): \App\Core\Models\CartItem {
        if (!in_array($itemType, ['mix', 'creator_mix'])) {
            throw new \InvalidArgumentException("Invalid item type: {$itemType}");
        }

        // Foodics-native mix: the build is a single Foodics product (the
        // configured Build Your Mix product) plus selected modifier options.
        // Price = product base price + sum of selected option prices. We set
        // product_id so the line maps cleanly through the Foodics order push.
        if (!empty($configuration['foodics_option_ids'])) {
            return $this->addFoodicsMixToCart($cart, $itemType, $configuration, $quantity, $refId, $name, $note);
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
            'note' => $note,
        ];

        return $this->cartRepository->addItemUnified($cart, $payload);
    }

    /**
     * Add a Foodics-native build-your-mix to the cart. The mix resolves to the
     * configured Build Your Mix product (config mix.foodics_product_id) plus the
     * selected Foodics modifier option ids. Stored with product_id set so the
     * order push can map it to Foodics; configuration carries the option ids.
     *
     * @throws \InvalidArgumentException
     */
    private function addFoodicsMixToCart(
        Cart $cart,
        string $itemType,
        array $configuration,
        int $quantity,
        ?int $refId,
        ?string $name,
        ?string $note
    ): \App\Core\Models\CartItem {
        $mixProductId = config('mix.foodics_product_id');
        $product = $mixProductId ? Product::find($mixProductId) : null;

        if (!$product || !$product->is_active) {
            throw new \InvalidArgumentException('Build Your Mix product is not configured or inactive.');
        }

        $optionIds = array_values(array_unique(array_map(
            'intval',
            (array) $configuration['foodics_option_ids']
        )));

        $optionsTotal = (float) \App\Core\Models\FoodicsModifierOption::query()
            ->whereIn('id', $optionIds)
            ->where('is_active', true)
            ->sum('price');

        $price = round((float) $product->base_price + $optionsTotal, 2);

        if (!$name) {
            $name = $itemType === 'creator_mix' ? 'Creator Mix' : 'Custom Mix';
        }

        $payload = [
            'product_id'    => $product->id,
            'quantity'      => $quantity,
            'price'         => $price,
            'item_type'     => $itemType,
            'ref_id'        => $refId,
            'name'          => $name,
            'configuration' => ['foodics_option_ids' => $optionIds],
            'note'          => $note,
        ];

        return $this->cartRepository->addItemUnified($cart, $payload);
    }
}

