<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    /**
     * For Foodics-synced products, record which fields the admin actually
     * touched so the recurring catalog sync skips overwriting them. We
     * compare the incoming form data against the current DB row, union the
     * dirty keys with whatever was already in `locally_overridden_fields`,
     * and persist the merged set. Local-only products skip this entirely.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->record;
        if (($record->external_source ?? null) !== 'foodics') {
            return $data;
        }

        $tracked = [
            'category_id',
            'name_json',
            'description_json',
            'image',
            'base_price',
            'is_active',
            'sort_order',
            'product_kind',
            'allergens',
            'caffeine_mg',
            'caffeine_level',
        ];

        $existing = $record->locally_overridden_fields ?? [];
        $existing = is_array($existing) ? $existing : [];
        $dirty = [];
        foreach ($tracked as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            // Compare against the current DB value via the model accessor
            // so JSON casts (name_json, allergens) are normalized on both
            // sides — avoids spurious "dirty" flags from array shape diffs.
            if ($data[$field] != $record->getAttribute($field)) {
                $dirty[] = $field;
            }
        }
        $data['locally_overridden_fields'] = array_values(array_unique(
            array_merge($existing, $dirty)
        ));
        return $data;
    }
}
