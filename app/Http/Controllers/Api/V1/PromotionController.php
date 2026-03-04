<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Models\Promotion;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PromotionResource;
use Illuminate\Http\JsonResponse;

class PromotionController extends Controller
{
    public function index(): JsonResponse
    {
        $promotions = Promotion::active()->orderBy('sort_order')->orderBy('id')->get();

        return apiSuccess(PromotionResource::collection($promotions));
    }

    public function show(int $id): JsonResponse
    {
        $promotion = Promotion::active()->findOrFail($id);

        return apiSuccess(new PromotionResource($promotion));
    }
}
