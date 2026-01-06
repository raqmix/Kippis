<?php

namespace App\Http\Resources\Api\V1;

use App\Core\Enums\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Map status from enum to translated label
        $statusEnum = OrderStatus::tryFrom($this->status);
        $statusLabel = $statusEnum ? $statusEnum->label() : $this->status;

        return [
            'id' => $this->id,
            'status' => $this->status,
            'status_label' => $statusLabel,
            'pickup_code' => $this->pickup_code,
            'status_history' => $this->buildStatusHistory(),
            'total' => (float) $this->total,
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'payment_method' => $this->payment_method, // Keep for backward compatibility
            'payment_method_id' => $this->payment_method_id,
            'payment_method_details' => $this->when($this->relationLoaded('paymentMethod') && $this->paymentMethod, function () {
                return [
                    'id' => $this->paymentMethod->id,
                    'name' => $this->paymentMethod->name,
                    'code' => $this->paymentMethod->code,
                ];
            }),
            'items' => $this->items_snapshot,
            'modifiers' => $this->modifiers_snapshot,
            'promo_code' => $this->when($this->relationLoaded('promoCode') && $this->promoCode, function () {
                return [
                    'code' => $this->promoCode->code,
                    'discount' => (float) $this->promo_discount,
                ];
            }),
            'store' => $this->whenLoaded('store', function () {
                return [
                    'id' => $this->store->id,
                    'name' => $this->store->name,
                ];
            }),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    /**
     * Build status history with all possible statuses.
     *
     * @return array
     */
    protected function buildStatusHistory(): array
    {
        $statusHistory = [];
        
        // Define normal progression order (cancelled can happen at any point)
        $normalProgression = [
            OrderStatus::RECEIVED->value,
            OrderStatus::MIXING->value,
            OrderStatus::READY->value,
            OrderStatus::COMPLETED->value,
        ];

        $isCancelled = $this->status === OrderStatus::CANCELLED->value;
        
        // Determine which statuses are completed
        $completedStatuses = [];
        if ($isCancelled) {
            // If cancelled, only received and cancelled are completed
            $completedStatuses = [OrderStatus::RECEIVED->value, OrderStatus::CANCELLED->value];
        } else {
            // For normal progression, all statuses up to current are completed
            $currentIndex = array_search($this->status, $normalProgression);
            if ($currentIndex !== false) {
                $completedStatuses = array_slice($normalProgression, 0, $currentIndex + 1);
            }
        }

        // Build history for normal progression statuses
        foreach ($normalProgression as $index => $statusValue) {
            $statusEnum = OrderStatus::tryFrom($statusValue);
            $statusLabel = $statusEnum ? $statusEnum->label() : $statusValue;
            $isCompleted = in_array($statusValue, $completedStatuses);
            
            // Calculate timestamp: received at created_at, others estimated
            $statusTime = null;
            if ($isCompleted) {
                if ($statusValue === OrderStatus::RECEIVED->value) {
                    $statusTime = $this->created_at;
                } else {
                    // Estimate based on time difference
                    $timeDiff = $this->updated_at->diffInSeconds($this->created_at);
                    $completedCount = count($completedStatuses);
                    if ($completedCount > 1) {
                        $estimatedSeconds = ($timeDiff / ($completedCount - 1)) * $index;
                        $statusTime = $this->created_at->copy()->addSeconds($estimatedSeconds);
                    } else {
                        // Fallback: use updated_at if only one status completed
                        $statusTime = $this->updated_at;
                    }
                }
            }

            $statusHistory[] = [
                'status' => $statusValue,
                'status_label' => $statusLabel,
                'at' => $statusTime ? $statusTime->toIso8601String() : null,
                'completed' => $isCompleted,
            ];
        }

        // Add cancelled status (always last)
        $cancelledEnum = OrderStatus::CANCELLED;
        $cancelledCompleted = in_array(OrderStatus::CANCELLED->value, $completedStatuses);
        $cancelledTime = $cancelledCompleted ? $this->updated_at : null;

        $statusHistory[] = [
            'status' => OrderStatus::CANCELLED->value,
            'status_label' => $cancelledEnum->label(),
            'at' => $cancelledTime ? $cancelledTime->toIso8601String() : null,
            'completed' => $cancelledCompleted,
        ];

        return $statusHistory;
    }
}

