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
            'size' => $modifiers->get('size', collect()),
            'smothing' => $modifiers->get('smothing', collect()),
            'customize_modifires' => $modifiers->get('customize_modifires', collect()),
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

