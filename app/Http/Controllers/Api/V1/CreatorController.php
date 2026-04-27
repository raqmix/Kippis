<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Models\Creator;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CreatorController extends Controller
{
    /**
     * GET /api/v1/creators — all active creators
     */
    public function index(): JsonResponse
    {
        $locale = app()->getLocale();

        $creators = Creator::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Creator $c) => [
                'id'            => $c->id,
                'name'          => $locale === 'ar' ? $c->name_ar : $c->name_en,
                'bio'           => $locale === 'ar' ? $c->bio_ar : $c->bio_en,
                'avatar'        => $c->avatar,
                'social_handle' => $c->social_handle,
            ]);

        return apiSuccess(['creators' => $creators]);
    }

    /**
     * GET /api/v1/creators/{creator}
     */
    public function show(Creator $creator): JsonResponse
    {
        if (! $creator->is_active) {
            return apiError('NOT_FOUND', 'Creator not found.', 404);
        }

        $locale = app()->getLocale();
        $creator->load('drops');

        $liveDrop = $creator->drops->firstWhere('status', 'live');

        return apiSuccess([
            'creator' => [
                'id'            => $creator->id,
                'name'          => $locale === 'ar' ? $creator->name_ar : $creator->name_en,
                'bio'           => $locale === 'ar' ? $creator->bio_ar : $creator->bio_en,
                'avatar'        => $creator->avatar,
                'social_handle' => $creator->social_handle,
                'active_drop'   => $liveDrop ? $liveDrop->only(['id', 'title_en', 'title_ar', 'starts_at', 'ends_at']) : null,
            ],
        ]);
    }
}
