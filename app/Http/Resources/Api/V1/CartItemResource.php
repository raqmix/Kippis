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
        ];

        // Handle product items
        if ($itemType === 'product') {
            $base['product'] = $this->whenLoaded('product', function () {
                return [
                    'id' => $this->product->id,
                    'name' => $this->product->getName(app()->getLocale()),
                    'image' => $this->product->image ? asset('storage/' . $this->product->image) : null,
                ];
            });
            
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

