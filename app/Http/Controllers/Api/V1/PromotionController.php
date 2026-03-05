<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Models\Promotion;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PromotionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Promotion::active()->with('product')->orderBy('sort_order')->orderBy('id');
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }
        $promotions = $query->get();

        return apiSuccess(PromotionResource::collection($promotions));
    }

    public function show(int $id): JsonResponse
    {
        $promotion = Promotion::active()->with('product')->findOrFail($id);

        return apiSuccess(new PromotionResource($promotion));
    }
}
