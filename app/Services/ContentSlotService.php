<?php

namespace App\Services;

use App\Core\Models\ContentSlot;
use Illuminate\Support\Collection;

class ContentSlotService
{
    /**
     * Get active content slots for a platform, optionally filtered to specific slot keys.
     *
     * @param string        $platform  'web' | 'mobile' | 'kiosk'
     * @param string[]|null $keys      Optional list of slot_key values to filter
     * @return Collection<string, Collection<ContentSlot>>  Keyed by slot_key
     */
    public function getSlots(string $platform, ?array $keys = null): Collection
    {
        $query = ContentSlot::active()
            ->forPlatform($platform)
            ->orderBy('slot_key')
            ->orderBy('sort_order');

        if ($keys !== null) {
            $query->whereIn('slot_key', $keys);
        }

        return $query->get()->groupBy('slot_key');
    }
}
