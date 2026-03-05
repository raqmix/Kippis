<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Models\Promotion;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PromotionResource;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Promotion::where('is_active', true)->with('product')->orderBy('sort_order')->orderBy('id');
        if ($request->filled('date')) {
            $d = Carbon::parse($request->date);
            $query->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $d->copy()->endOfDay()))
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $d->copy()->startOfDay()));
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
