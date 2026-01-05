<?php

namespace App\Services;

use App\Core\Models\Modifier;
use App\Core\Models\Product;
use App\Core\Models\MixBuilderBase;

class MixPriceCalculator
{
    /**
     * Calculate total price for a mix configuration.
     *
     * @param array $configuration Configuration array with base_id/base_price, modifiers, and extras
     * @return array Array with 'total' and 'breakdown' keys
     * @throws \InvalidArgumentException
     */
    public function calculate(array $configuration): array
    {
        $this->validateConfiguration($configuration);
        
        $breakdown = [];
        $total = 0;
        $baseProduct = null;

        // Calculate base price
        $basePrice = $this->calculateBasePrice($configuration, $baseProduct);
        if ($basePrice > 0) {
            $baseLabel = $baseProduct ? $baseProduct->getName(app()->getLocale()) : 'Base';
            $breakdown[] = [
                'label' => $baseLabel,
                'amount' => round($basePrice, 2),
                'type' => 'base',
            ];
            $total += $basePrice;
        }

        // Calculate modifier prices
        if (isset($configuration['modifiers']) && is_array($configuration['modifiers'])) {
            foreach ($configuration['modifiers'] as $modifierConfig) {
                $modifierResult = $this->calculateModifierPrice($modifierConfig);
                if ($modifierResult['price'] > 0) {
                    $breakdown[] = [
                        'label' => $modifierResult['label'],
                        'amount' => round($modifierResult['price'], 2),
                        'type' => 'modifier',
                        'modifier_id' => $modifierResult['modifier_id'],
                        'level' => $modifierResult['level'] ?? null,
                    ];
                    $total += $modifierResult['price'];
                }
            }
        }

        // Calculate extra product/modifier prices
        if (isset($configuration['extras']) && is_array($configuration['extras'])) {
            foreach ($configuration['extras'] as $extraId) {
                $extraResult = $this->calculateExtraPrice($extraId);
                if ($extraResult['price'] > 0) {
                    $breakdown[] = [
                        'label' => $extraResult['label'],
                        'amount' => round($extraResult['price'], 2),
                        'type' => 'extra',
                        'product_id' => $extraResult['product_id'] ?? null,
                        'modifier_id' => $extraResult['modifier_id'] ?? null,
                    ];
                    $total += $extraResult['price'];
                }
            }
        }

        return [
            'total' => round($total, 2),
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Calculate price for a product with addons.
     *
     * @param Product $product
     * @param array $addons Array of addon configurations: [{modifier_id, level}]
     * @return array Array with 'total' and 'breakdown' keys
     * @throws \InvalidArgumentException
     */
    public function calculateProductWithAddons(Product $product, array $addons = []): array
    {
        if (!$product->is_active) {
            throw new \InvalidArgumentException('Product is not active.');
        }

        $breakdown = [];
        $total = (float) $product->base_price;

        $breakdown[] = [
            'label' => $product->getName(app()->getLocale()),
            'amount' => round($total, 2),
            'type' => 'product',
        ];

        if (!empty($addons)) {
            $this->validateAddons($product, $addons);

            foreach ($addons as $addon) {
                $modifierResult = $this->calculateModifierPrice($addon);
                if ($modifierResult['price'] > 0) {
                    $breakdown[] = [
                        'label' => $modifierResult['label'],
                        'amount' => round($modifierResult['price'], 2),
                        'type' => 'addon',
                        'modifier_id' => $modifierResult['modifier_id'],
                        'level' => $modifierResult['level'] ?? null,
                    ];
                    $total += $modifierResult['price'];
                }
            }
        }

        return [
            'total' => round($total, 2),
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Validate configuration structure.
     *
     * @param array $configuration
     * @throws \InvalidArgumentException
     */
    protected function validateConfiguration(array $configuration): void
    {
        // Validate modifiers array structure
        if (isset($configuration['modifiers'])) {
            if (!is_array($configuration['modifiers'])) {
                throw new \InvalidArgumentException('Modifiers must be an array.');
            }

            foreach ($configuration['modifiers'] as $index => $modifierConfig) {
                if (!is_array($modifierConfig)) {
                    throw new \InvalidArgumentException("Modifier at index {$index} must be an object.");
                }

                if (!isset($modifierConfig['id'])) {
                    throw new \InvalidArgumentException("Modifier at index {$index} must have an 'id' field.");
                }

                if (!is_numeric($modifierConfig['id'])) {
                    throw new \InvalidArgumentException("Modifier ID at index {$index} must be numeric.");
                }
            }
        }

        // Validate extras array
        if (isset($configuration['extras'])) {
            if (!is_array($configuration['extras'])) {
                throw new \InvalidArgumentException('Extras must be an array.');
            }

            foreach ($configuration['extras'] as $index => $extraId) {
                if (!is_numeric($extraId)) {
                    throw new \InvalidArgumentException("Extra at index {$index} must be a numeric product ID.");
                }
            }
        }
    }

    /**
     * Validate addons for a product.
     *
     * @param Product $product
     * @param array $addons
     * @throws \InvalidArgumentException
     */
    protected function validateAddons(Product $product, array $addons): void
    {
        $assignedModifierIds = $product->addonModifiers()->pluck('modifiers.id')->toArray();

        foreach ($addons as $index => $addon) {
            if (!isset($addon['modifier_id']) && !isset($addon['id'])) {
                throw new \InvalidArgumentException("Addon at index {$index} must have 'modifier_id' or 'id' field.");
            }

            $modifierId = $addon['modifier_id'] ?? $addon['id'];

            if (!in_array($modifierId, $assignedModifierIds)) {
                throw new \InvalidArgumentException(
                    "Modifier ID {$modifierId} is not assigned to product {$product->id} as an addon."
                );
            }

            // Check min/max constraints if defined
            $pivot = $product->addonModifiers()->where('modifiers.id', $modifierId)->first()?->pivot;
            if ($pivot) {
                $level = $addon['level'] ?? 0;
                if ($pivot->min_select !== null && $level < $pivot->min_select) {
                    throw new \InvalidArgumentException(
                        "Modifier {$modifierId} requires minimum level {$pivot->min_select}."
                    );
                }
                if ($pivot->max_select !== null && $level > $pivot->max_select) {
                    throw new \InvalidArgumentException(
                        "Modifier {$modifierId} requires maximum level {$pivot->max_select}."
                    );
                }
            }
        }
    }

    /**
     * Calculate base price from configuration.
     *
     * @param array $configuration
     * @param Product|null $baseProduct Reference to base product (set by this method)
     * @return float
     * @throws \InvalidArgumentException
     */
    protected function calculateBasePrice(array $configuration, ?Product &$baseProduct = null): float
    {
        // Try to get base_id first (preferred)
        if (isset($configuration['base_id']) && $configuration['base_id']) {
            $baseProduct = Product::active()->find($configuration['base_id']);
            
            if (!$baseProduct) {
                throw new \InvalidArgumentException('Base product not found or inactive.');
            }

            // Validate base is a mix_base product
            if ($baseProduct->product_kind !== 'mix_base') {
                throw new \InvalidArgumentException('Base product must be of kind mix_base.');
            }

            // Validate base belongs to builder if builder_id is provided
            $builderId = $configuration['builder_id'] ?? $configuration['mix_builder_id'] ?? null;
            if ($builderId !== null) {
                $this->validateBaseForBuilder($baseProduct->id, $builderId);
            }

            return (float) $baseProduct->base_price;
        }

        // Fallback to base_price if provided (deprecated, for backward compatibility)
        if (isset($configuration['base_price']) && $configuration['base_price'] !== null) {
            // Log warning for deprecated usage (in production, use logger)
            // \Log::warning('Using deprecated base_price in mix configuration. Use base_id instead.');
            
            $basePrice = (float) $configuration['base_price'];
            
            if ($basePrice < 0) {
                throw new \InvalidArgumentException('Base price cannot be negative.');
            }

            return $basePrice;
        }

        // If neither is provided, throw exception
        throw new \InvalidArgumentException('Either base_id or base_price must be provided.');
    }

    /**
     * Validate that a base product belongs to a specific builder.
     *
     * @param int $baseId
     * @param int|null $builderId
     * @throws \InvalidArgumentException
     */
    protected function validateBaseForBuilder(int $baseId, ?int $builderId): void
    {
        if ($builderId === null) {
            // No builder specified, allow any mix_base product (global bases)
            return;
        }

        // Check if base is assigned to this builder (or is global - mix_builder_id is null)
        $isAssigned = MixBuilderBase::where('product_id', $baseId)
            ->where(function ($query) use ($builderId) {
                $query->where('mix_builder_id', $builderId)
                      ->orWhereNull('mix_builder_id'); // Global bases (null) available to all builders
            })
            ->exists();

        if (!$isAssigned) {
            throw new \InvalidArgumentException(
                "Base product {$baseId} is not assigned to builder {$builderId}."
            );
        }
    }

    /**
     * Calculate modifier price.
     *
     * @param array $modifierConfig Modifier configuration with 'id' or 'modifier_id' and optional 'level'
     * @return array Array with 'price', 'label', 'modifier_id', 'level'
     * @throws \InvalidArgumentException
     */
    protected function calculateModifierPrice(array $modifierConfig): array
    {
        // Support both 'id' and 'modifier_id' for flexibility
        $modifierId = $modifierConfig['id'] ?? $modifierConfig['modifier_id'] ?? null;

        if (!$modifierId) {
            throw new \InvalidArgumentException('Modifier ID is required (use "id" or "modifier_id").');
        }

        $modifier = Modifier::active()->find($modifierId);

        if (!$modifier) {
            throw new \InvalidArgumentException("Modifier with ID {$modifierId} not found or inactive.");
        }

        // Normalize level: default to 1 if not provided, but allow 0
        $level = isset($modifierConfig['level']) ? (int) $modifierConfig['level'] : 1;

        // Validate level is non-negative
        if ($level < 0) {
            throw new \InvalidArgumentException('Modifier level cannot be negative.');
        }

        // Validate level doesn't exceed max_level (if max_level is defined)
        if ($modifier->max_level !== null && $level > $modifier->max_level) {
            throw new \InvalidArgumentException(
                "Modifier level {$level} exceeds maximum level {$modifier->max_level} for modifier {$modifierId}."
            );
        }

        $price = (float) ($modifier->price * $level);
        $label = $modifier->getName(app()->getLocale());
        
        // Add level info to label if level > 1
        if ($level > 1 && $modifier->max_level !== null) {
            $label .= " (Level {$level})";
        }

        return [
            'price' => $price,
            'label' => $label,
            'modifier_id' => $modifierId,
            'level' => $level,
        ];
    }

    /**
     * Calculate extra price (product or modifier of type "extra").
     *
     * @param int $extraId Product ID or modifier ID (if modifier, must be type "extra")
     * @return array Array with 'price', 'label', and optionally 'product_id' or 'modifier_id'
     * @throws \InvalidArgumentException
     */
    protected function calculateExtraPrice(int $extraId): array
    {
        // First, try to find as a product
        $product = Product::active()->find($extraId);
        if ($product) {
            return [
                'price' => (float) $product->base_price,
                'label' => $product->getName(app()->getLocale()),
                'product_id' => $product->id,
            ];
        }

        // If not a product, try to find as a modifier with type "extra"
        $modifier = Modifier::active()->where('id', $extraId)->where('type', 'extra')->first();
        if ($modifier) {
            return [
                'price' => (float) $modifier->price,
                'label' => $modifier->getName(app()->getLocale()),
                'modifier_id' => $modifier->id,
            ];
        }

        throw new \InvalidArgumentException("Extra with ID {$extraId} not found. It must be a valid product ID or a modifier ID with type 'extra'.");
    }
}

