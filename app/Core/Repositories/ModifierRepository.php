<?php

namespace App\Core\Repositories;

use App\Core\Models\Modifier;
use Illuminate\Database\Eloquent\Collection;

class ModifierRepository
{
    /**
     * Get all active modifiers grouped by type.
     */
    public function getGroupedByType(): array
    {
        $modifiers = Modifier::active()->get()->groupBy('type');

        return [
            'sweetness' => $modifiers->get('sweetness', collect()),
            'fizz' => $modifiers->get('fizz', collect()),
            'caffeine' => $modifiers->get('caffeine', collect()),
            'extra' => $modifiers->get('extra', collect()),
        ];
    }

    /**
     * Find modifier by ID.
     */
    public function findById(int $id): ?Modifier
    {
        return Modifier::active()->find($id);
    }

    /**
     * Get modifiers by type.
     */
    public function getByType(string $type): Collection
    {
        return Modifier::active()->ofType($type)->get();
    }
}

