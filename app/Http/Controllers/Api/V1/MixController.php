<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Repositories\ModifierRepository;
use App\Core\Models\Product;
use App\Core\Models\MixBuilderBase;
use App\Services\MixPriceCalculator;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PreviewMixRequest;
use App\Http\Resources\Api\V1\ModifierResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Mix Builder APIs
 */
class MixController extends Controller
{
    public function __construct(
        private ModifierRepository $modifierRepository,
        private MixPriceCalculator $mixPriceCalculator
    ) {
    }

    /**
     * Get mix builder options
     *
     * Returns product details for the first base product associated with the builder,
     * including all available modifiers as allowed_addons.
     * Uses the same format as /api/v1/catalog/products/:id.
     *
     * @queryParam builder_id integer required Filter bases by specific builder ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 54,
     *     "name": "Chocolate Cake",
     *     "name_ar": "كعكة الشوكولاتة",
     *     "name_en": "Chocolate Cake",
     *     "description": "Rich chocolate cake slice",
     *     "description_ar": "شريحة كعكة شوكولاتة غنية",
     *     "description_en": "Rich chocolate cake slice",
     *     "image": null,
     *     "base_price": 30,
     *     "category": {
     *       "id": 5,
     *       "name": "Desserts"
     *     },
     *     "external_source": "local",
     *     "allowed_addons": [
     *       {
     *         "id": 49,
     *         "modifier_id": 49,
     *         "name": "Size",
     *         "type": "size",
     *         "max_level": null,
     *         "price": 0,
     *         "is_required": true,
     *         "min_select": null,
     *         "max_select": null
     *       }
     *     ]
     *   }
     * }
     */
    public function options(Request $request): JsonResponse
    {
        $builderId = $request->query('builder_id');

        if (!$builderId) {
            return apiError('BUILDER_ID_REQUIRED', 'builder_id parameter is required', 400);
        }

        // Get the first base product for this builder
        $baseIds = MixBuilderBase::where(function ($query) use ($builderId) {
            $query->where('mix_builder_id', $builderId)
                  ->orWhereNull('mix_builder_id'); // Global bases (null) available to all
        })->pluck('product_id');

        $product = Product::with('category')
            ->active()
            ->mixBases()
            ->whereIn('id', $baseIds)
            ->first();

        if (!$product) {
            return apiError('NO_BASE_FOUND', 'No base product found for the specified builder_id', 404);
        }

        // Get all modifiers grouped by type and flatten them into allowed_addons
        $modifiers = $this->modifierRepository->getGroupedByType();

        $allowedAddons = collect();
        foreach (['size', 'smothing', 'customize_modifires', 'extra'] as $type) {
            $typeModifiers = $modifiers[$type] ?? collect();
            foreach ($typeModifiers as $modifier) {
                $allowedAddons->push([
                    'id' => $modifier->id,
                    'modifier_id' => $modifier->id,
                    'name' => $modifier->getName(app()->getLocale()),
                    'type' => $modifier->type,
                    'max_level' => $modifier->max_level,
                    'price' => (float) $modifier->price,
                    'is_required' => false, // Default to false for mix builder modifiers
                    'min_select' => null,
                    'max_select' => null,
                ]);
            }
        }

        // Build response in the same format as ProductResource
        $data = [
            'id' => $product->id,
            'name' => $product->getName(app()->getLocale()),
            'name_ar' => $product->getName('ar'),
            'name_en' => $product->getName('en'),
            'description' => $product->getDescription(app()->getLocale()),
            'description_ar' => $product->getDescription('ar'),
            'description_en' => $product->getDescription('en'),
            'image' => $product->image ? asset('storage/' . $product->image) : null,
            'base_price' => (float) $product->base_price,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->getName(app()->getLocale()),
            ] : null,
            'external_source' => $product->external_source,
            'allowed_addons' => $allowedAddons->values()->all(),
        ];

        return apiSuccess($data);
    }

    /**
     * Preview mix price calculation
     *
     * Calculate the total price and breakdown for a mix configuration **without adding to cart**.
     * This endpoint validates the configuration and returns pricing details for preview purposes.
     *
     * **Modifier Levels**: Each modifier has a `max_level`. The level must be between 0 and `max_level`.
     * - Level 0: No modifier applied (price = 0)
     * - Level 1-N: Price = `modifier.price * level`
     *
     * **Request Example**:
     * ```json
     * {
     *   "configuration": {
     *     "base_id": 1,
     *     "modifiers": [
     *       {"id": 2, "level": 3},
     *       {"id": 5, "level": 1}
     *     ],
     *     "extras": [3, 4]
     *   }
     * }
     * ```
     *
     * @bodyParam configuration object required Configuration snapshot for the mix. Example: {"base_id":1,"modifiers":[{"id":2,"level":1}],"extras":[3]}
     * @bodyParam configuration.base_id integer optional Base product ID (preferred). Must be a product with product_kind = mix_base. Example: 1
     * @bodyParam configuration.base_price number optional Deprecated. Raw base price for backward compatibility. Example: 15.00
     * @bodyParam configuration.builder_id integer optional Mix builder ID to validate base belongs to builder. Example: 1
     * @bodyParam configuration.mix_builder_id integer optional Alias for builder_id. Example: 1
     * @bodyParam configuration.modifiers array optional Array of modifier configurations. Example: [{"id": 2, "level": 3}]
     * @bodyParam configuration.modifiers.*.id integer required Modifier ID. Example: 2
     * @bodyParam configuration.modifiers.*.level integer optional Modifier level (0 to max_level). Default: 1. Example: 3
     * @bodyParam configuration.extras array optional Array of extra product IDs. Example: [3,4]
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "total": 25.50,
     *     "breakdown": [
     *       {"label": "Product Name", "amount": 15.00, "type": "base"},
     *       {"label": "Sweetness (Level 3)", "amount": 4.50, "type": "modifier", "modifier_id": 2, "level": 3},
     *       {"label": "Extra Product", "amount": 6.00, "type": "extra", "product_id": 3}
     *     ]
     *   }
     * }
     *
     * @response 400 {
     *   "success": false,
     *   "error": "INVALID_CONFIGURATION",
     *   "message": "Modifier level 5 exceeds maximum level 3 for modifier 2"
     * }
     */
    public function preview(PreviewMixRequest $request): JsonResponse
    {
        $configuration = $request->input('configuration', []);

        try {
            $result = $this->mixPriceCalculator->calculate($configuration);
        } catch (\InvalidArgumentException $e) {
            return apiError('INVALID_CONFIGURATION', $e->getMessage(), 400);
        } catch (\Exception $e) {
            return apiError('ERROR', 'An error occurred while calculating price.', 500);
        }

        return apiSuccess(['total' => $result['total'], 'breakdown' => $result['breakdown']]);
    }
}
