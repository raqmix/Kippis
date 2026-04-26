<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ContentSlotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentSlotController extends Controller
{
    public function __construct(private readonly ContentSlotService $slots) {}

    /**
     * GET /api/v1/content-slots?platform=mobile&keys[]=home_hero&keys[]=home_banner_1
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'platform' => ['nullable', 'in:web,mobile,kiosk'],
            'keys'     => ['nullable', 'array'],
            'keys.*'   => ['string', 'max:50'],
        ]);

        $platform = $request->input('platform', 'web');
        $keys     = $request->input('keys');

        $grouped = $this->slots->getSlots($platform, $keys);

        $data = $grouped->map(fn ($slotGroup) => $slotGroup->map(fn ($slot) => [
            'id'         => $slot->id,
            'slot_key'   => $slot->slot_key,
            'title'      => app()->getLocale() === 'ar' ? $slot->title_ar : $slot->title_en,
            'title_en'   => $slot->title_en,
            'title_ar'   => $slot->title_ar,
            'subtitle'   => app()->getLocale() === 'ar' ? $slot->subtitle_ar : $slot->subtitle_en,
            'image'      => $slot->image,
            'cta_text'   => app()->getLocale() === 'ar' ? $slot->cta_text_ar : $slot->cta_text_en,
            'cta_action' => $slot->cta_action,
            'sort_order' => $slot->sort_order,
        ])->values()->all())->all();

        return apiSuccess(['data' => $data]);
    }
}
