<?php

namespace App\Services;

use App\Core\Models\CreatorDrop;
use App\Core\Models\Store;
use Illuminate\Support\Collection;

class CreatorDropService
{
    /**
     * Get currently live drops, optionally filtered to a specific store.
     */
    public function getActiveDrops(?Store $store = null): Collection
    {
        $query = CreatorDrop::live()
            ->with(['creator', 'product'])
            ->orderByDesc('starts_at');

        if ($store) {
            $query->where(function ($q) use ($store) {
                $q->whereNull('store_ids')
                  ->orWhereJsonContains('store_ids', $store->id);
            });
        }

        return $query->get();
    }

    /**
     * Get scheduled (upcoming) drops.
     */
    public function getUpcomingDrops(): Collection
    {
        return CreatorDrop::scheduled()
            ->with(['creator', 'product'])
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * Transition drops through their lifecycle — called by the scheduler.
     */
    public function runLifecycle(): void
    {
        // Activate scheduled drops whose start time has passed
        CreatorDrop::where('status', 'scheduled')
            ->where('starts_at', '<=', now())
            ->get()
            ->each(function (CreatorDrop $drop) {
                $drop->update(['status' => 'live']);
            });

        // End live drops whose end time has passed
        CreatorDrop::where('status', 'live')
            ->where('ends_at', '<=', now())
            ->get()
            ->each(function (CreatorDrop $drop) {
                $drop->update(['status' => 'ended']);
            });
    }
}
