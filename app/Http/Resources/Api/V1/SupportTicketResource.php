<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportTicketResource extends JsonResource
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
            'ticket_number' => $this->ticket_number,
            'subject' => $this->subject,
            'message' => $this->message,
            'status' => $this->status,
            'priority' => $this->priority,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'replies' => $this->whenLoaded('replies', function () {
                return $this->replies->map(function ($reply) {
                    return [
                        'id' => $reply->id,
                        'message' => $reply->message,
                        'is_internal' => $reply->is_internal,
                        'created_at' => $reply->created_at?->toIso8601String(),
                        'admin' => $reply->admin ? [
                            'id' => $reply->admin->id,
                            'name' => $reply->admin->name,
                        ] : null,
                    ];
                });
            }),
        ];
    }
}

