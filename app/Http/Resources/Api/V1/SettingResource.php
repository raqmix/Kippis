<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $value = $this->value;
        
        // Cast value based on type
        switch ($this->type) {
            case 'boolean':
                $value = $this->value === '1' || $this->value === 'true' || $this->value === true;
                break;
            case 'json':
                $value = json_decode($this->value, true);
                break;
            case 'number':
            case 'integer':
                $value = is_numeric($this->value) ? (int) $this->value : 0;
                break;
            case 'float':
            case 'decimal':
                $value = is_numeric($this->value) ? (float) $this->value : 0.0;
                break;
        }

        return [
            'id' => $this->id,
            'key' => $this->key,
            'value' => $value,
            'type' => $this->type,
            'group' => $this->group,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

