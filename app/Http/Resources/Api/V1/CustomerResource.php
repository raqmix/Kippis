<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'country_code' => $this->country_code,
            'birthdate' => $this->birthdate?->format('Y-m-d'),
            'avatar' => $this->avatar ? asset('storage/' . $this->avatar) : null,
            'foodics_customer_id' => $this->foodics_customer_id,
            'is_verified' => $this->is_verified,
            'is_verified_label' => $this->is_verified ? __('api.verified') : __('api.unverified'),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'locale' => app()->getLocale(),
                'field_labels' => [
                    'id' => __('api.id'),
                    'name' => __('api.name'),
                    'email' => __('api.email'),
                    'phone' => __('api.phone'),
                    'country_code' => __('api.country_code'),
                    'birthdate' => __('api.birthdate'),
                    'avatar' => __('api.avatar'),
                    'foodics_customer_id' => __('api.foodics_customer_id'),
                    'is_verified' => __('api.is_verified'),
                    'created_at' => __('api.created_at'),
                    'updated_at' => __('api.updated_at'),
                ],
            ],
        ];
    }
}
