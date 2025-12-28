<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Repositories\ModifierRepository;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ModifierResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MixController extends Controller
{
    public function __construct(
        private ModifierRepository $modifierRepository
    ) {
    }

    public function options(): JsonResponse
    {
        $modifiers = $this->modifierRepository->getGroupedByType();

        $data = [];
        foreach (['sweetness', 'fizz', 'caffeine', 'extra'] as $type) {
            $data[$type] = ModifierResource::collection($modifiers[$type]);
        }

        return apiSuccess($data);
    }

    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'base_price' => 'required|numeric|min:0',
            'modifiers' => 'array',
            'modifiers.*.id' => 'required|exists:modifiers,id',
            'modifiers.*.level' => 'nullable|integer|min:1',
        ]);

        $basePrice = (float) $request->input('base_price');
        $modifiers = $request->input('modifiers', []);
        $totalModifierPrice = 0;

        foreach ($modifiers as $modifierData) {
            $modifier = $this->modifierRepository->findById($modifierData['id']);
            if (!$modifier || !$modifier->is_active) {
                continue;
            }

            $level = $modifierData['level'] ?? 1;
            if ($modifier->max_level && $level > $modifier->max_level) {
                $level = $modifier->max_level;
            }

            $totalModifierPrice += $modifier->price * $level;
        }

        $total = $basePrice + $totalModifierPrice;

        return apiSuccess([
            'base_price' => $basePrice,
            'modifiers_price' => $totalModifierPrice,
            'total' => $total,
        ]);
    }
}
