<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Repositories\ModifierRepository;
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
     * Returns all available modifiers grouped by type (sweetness, fizz, caffeine, extra).
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "sweetness": [
     *       {
     *         "id": 1,
     *         "name": "Low Sugar",
     *         "price": 0.00
     *       }
     *     ],
     *     "fizz": [],
     *     "caffeine": [],
     *     "extra": []
     *   }
     * }
     */
    public function options(): JsonResponse
    {
        $modifiers = $this->modifierRepository->getGroupedByType();

        $data = [];
        foreach (['sweetness', 'fizz', 'caffeine', 'extra'] as $type) {
            $data[$type] = ModifierResource::collection($modifiers[$type]);
        }

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
     * @bodyParam configuration.base_id integer optional Base product ID (preferred). Example: 1
     * @bodyParam configuration.base_price number optional Deprecated. Raw base price for backward compatibility. Example: 15.00
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
