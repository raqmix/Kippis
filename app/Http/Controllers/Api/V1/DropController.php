<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Models\Creator;
use App\Core\Models\CreatorDrop;
use App\Http\Controllers\Controller;
use App\Services\CreatorDropService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DropController extends Controller
{
    public function __construct(private CreatorDropService $service) {}

    /**
     * GET /api/v1/drops — all live drops
     */
    public function index(Request $request): JsonResponse
    {
        $drops = $this->service->getActiveDrops();

        return apiSuccess([
            'drops' => $drops->map(fn (CreatorDrop $d) => $this->formatDrop($d)),
        ]);
    }

    /**
     * GET /api/v1/drops/{drop}
     */
    public function show(CreatorDrop $drop): JsonResponse
    {
        if ($drop->status !== 'live') {
            return apiError('NOT_LIVE', 'This drop is not currently active.', 404);
        }

        $drop->load(['creator', 'product']);
        return apiSuccess(['drop' => $this->formatDrop($drop)]);
    }

    private function formatDrop(CreatorDrop $drop): array
    {
        $locale = app()->getLocale();

        return [
            'id'           => $drop->id,
            'title'        => $locale === 'ar' ? $drop->title_ar : $drop->title_en,
            'description'  => $locale === 'ar' ? $drop->description_ar : $drop->description_en,
            'cover_image'  => $drop->cover_image,
            'starts_at'    => $drop->starts_at?->toIso8601String(),
            'ends_at'      => $drop->ends_at?->toIso8601String(),
            'status'       => $drop->status,
            'max_quantity' => $drop->max_quantity,
            'quantity_sold'=> $drop->quantity_sold,
            'creator'      => $drop->creator ? [
                'id'             => $drop->creator->id,
                'name'           => $locale === 'ar' ? $drop->creator->name_ar : $drop->creator->name_en,
                'avatar'         => $drop->creator->avatar,
                'social_handle'  => $drop->creator->social_handle,
            ] : null,
            'product'      => $drop->product ? [
                'id'       => $drop->product->id,
                'name'     => $locale === 'ar' ? $drop->product->name_ar : $drop->product->name_en,
                'price'    => $drop->product->price,
                'image'    => $drop->product->image,
            ] : null,
        ];
    }
}
