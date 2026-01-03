<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $itemType = $this->item_type ?? 'product';
        
        $base = [
            'id' => $this->id,
            'item_type' => $itemType,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'price' => (float) $this->price,
            'note' => $this->note,
        ];

        // Handle product items
        if ($itemType === 'product') {
            // Check if full product details should be included (when product.addonModifiers is loaded)
            $includeFullProduct = $this->relationLoaded('product') && 
                                  $this->product && 
                                  $this->product->relationLoaded('addonModifiers');
            
            if ($includeFullProduct) {
                // Include full product details with allowed_addons
                $base['product'] = [
                    'id' => $this->product->id,
                    'name' => $this->product->getName(app()->getLocale()),
                    'name_ar' => $this->product->getName('ar'),
                    'name_en' => $this->product->getName('en'),
                    'description' => $this->product->getDescription(app()->getLocale()),
                    'description_ar' => $this->product->getDescription('ar'),
                    'description_en' => $this->product->getDescription('en'),
                    'image' => $this->product->image ? asset('storage/' . $this->product->image) : null,
                    'base_price' => (float) $this->product->base_price,
                    'category' => $this->product->whenLoaded('category', function () {
                        return [
                            'id' => $this->product->category->id,
                            'name' => $this->product->category->getName(app()->getLocale()),
                        ];
                    }),
                    'external_source' => $this->product->external_source,
                    'allowed_addons' => $this->product->addonModifiers->map(function ($modifier) {
                        return [
                            'id' => $modifier->id,
                            'modifier_id' => $modifier->id,
                            'name' => $modifier->getName(app()->getLocale()),
                            'type' => $modifier->type,
                            'max_level' => $modifier->max_level,
                            'price' => (float) $modifier->price,
                            'is_required' => (bool) ($modifier->pivot->is_required ?? false),
                            'min_select' => $modifier->pivot->min_select ?? null,
                            'max_select' => $modifier->pivot->max_select ?? null,
                        ];
                    })->values(),
                ];
            } else {
                // Include minimal product info (backward compatible)
                $base['product'] = $this->whenLoaded('product', function () {
                    return [
                        'id' => $this->product->id,
                        'name' => $this->product->getName(app()->getLocale()),
                        'image' => $this->product->image ? asset('storage/' . $this->product->image) : null,
                    ];
                });
            }
            
            // Include addons if present in configuration
            if ($this->configuration && isset($this->configuration['addons'])) {
                $base['addons'] = $this->configuration['addons'];
            }
            
            // Backward compatibility: include modifiers_snapshot if present
            if ($this->modifiers_snapshot) {
                $base['modifiers'] = $this->modifiers_snapshot;
            }
        } else {
            // Handle mix and creator_mix items
            $base['ref_id'] = $this->ref_id;
            
            if ($this->configuration) {
                $base['configuration'] = $this->configuration;
            }
        }

        return $base;
    }
}

