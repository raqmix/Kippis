<?php

namespace App\Http\Controllers\Api\V1\Kiosk;

use App\Http\Controllers\Controller;
use App\Services\CreatorDropService;
use App\Core\Models\CreatorDrop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KioskDropController extends Controller
{
    public function __construct(private CreatorDropService $service) {}

    /**
     * GET /api/v1/kiosk/drops — live drops available at this kiosk store
     */
    public function index(Request $request): JsonResponse
    {
        $store = $request->attributes->get('kiosk_store');
        $locale = app()->getLocale();

        $drops = $this->service->getActiveDrops($store);

        return apiSuccess([
            'drops' => $drops->map(fn (CreatorDrop $d) => [
                'id'          => $d->id,
                'title'       => $locale === 'ar' ? $d->title_ar : $d->title_en,
                'cover_image' => $d->cover_image,
                'ends_at'     => $d->ends_at?->toIso8601String(),
                'creator'     => $d->creator ? [
                    'id'     => $d->creator->id,
                    'name'   => $locale === 'ar' ? $d->creator->name_ar : $d->creator->name_en,
                    'avatar' => $d->creator->avatar,
                ] : null,
                'product'     => $d->product ? [
                    'id'    => $d->product->id,
                    'name'  => $locale === 'ar' ? $d->product->name_ar : $d->product->name_en,
                    'price' => $d->product->price,
                    'image' => $d->product->image,
                ] : null,
            ]),
        ]);
    }
}
