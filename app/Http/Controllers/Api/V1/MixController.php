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
     * Returns all available base products and modifiers grouped by type.
     *
     * @queryParam builder_id integer optional Filter bases by specific builder ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "bases": [
     *       {
     *         "id": 54,
     *         "name": "Chocolate Cake",
     *         "name_ar": "كعكة الشوكولاتة",
     *         "name_en": "Chocolate Cake",
     *         "image": null,
     *         "base_price": 30
     *       }
     *     ],
     *     "modifiers": {
     *       "size": [
     *         {
     *           "id": 49,
     *           "type": "size",
     *           "name": "Size",
     *           "name_ar": "الحجم",
     *           "name_en": "Size",
     *           "max_level": null,
     *           "price": 0
     *         }
     *       ],
     *       "smothing": [],
     *       "customize_modifires": [],
     *       "extra": []
     *     }
     *   }
     * }
     */
    public function options(Request $request): JsonResponse
    {
        $builderId = $request->query('builder_id');

        // Get base products query
        $basesQuery = Product::with('category')
            ->active()
            ->mixBases();

        // Filter by builder if provided
        if ($builderId) {
            $baseIds = MixBuilderBase::where(function ($query) use ($builderId) {
                $query->where('mix_builder_id', $builderId)
                      ->orWhereNull('mix_builder_id'); // Global bases (null) available to all
            })->pluck('product_id');

            if ($baseIds->isEmpty()) {
                // If no bases found for this builder, return empty bases array
                $bases = [];
            } else {
                $basesQuery->whereIn('id', $baseIds);
                $bases = $basesQuery->get()->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->getName(app()->getLocale()),
                        'name_ar' => $product->getName('ar'),
                        'name_en' => $product->getName('en'),
                        'image' => $this->getImageUrl($product->image),
                        'base_price' => (float) $product->base_price,
                    ];
                })->values()->all();
            }
        } else {
            // Get all base products if no builder_id specified
            $bases = $basesQuery->get()->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->getName(app()->getLocale()),
                    'name_ar' => $product->getName('ar'),
                    'name_en' => $product->getName('en'),
                    'image' => $this->getImageUrl($product->image),
                    'base_price' => (float) $product->base_price,
                ];
            })->values()->all();
        }

        // Get all modifiers grouped by type
        $modifiers = $this->modifierRepository->getGroupedByType();

        // Build modifiers object grouped by type
        $modifiersData = [];
        foreach (['size', 'smothing', 'customize_modifires', 'extra'] as $type) {
            $typeModifiers = $modifiers[$type] ?? collect();
            $modifiersData[$type] = $typeModifiers->map(function ($modifier) {
                return [
                    'id' => $modifier->id,
                    'type' => $modifier->type,
                    'name' => $modifier->getName(app()->getLocale()),
                    'name_ar' => $modifier->getName('ar'),
                    'name_en' => $modifier->getName('en'),
                    'max_level' => $modifier->max_level,
                    'price' => (float) $modifier->price,
                ];
            })->values()->all();
        }

        // Build response with bases array and modifiers
        $data = [
            'bases' => $bases ?? [], // Ensure bases is always an array, never null
            'modifiers' => $modifiersData,
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

    /**
     * Get the image URL, handling both local and external (Foodics) images.
     *
     * @param string|null $image
     * @return string|null
     */
    private function getImageUrl(?string $image): ?string
    {
        if (!$image) {
            return null;
        }

        // If the image is already a full URL (starts with http:// or https://), return as is
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }

        // Otherwise, it's a local image, prepend storage path
        return asset('storage/' . $image);
    }
}
