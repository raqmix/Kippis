<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Models\EventRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEventRequestRequest;
use Illuminate\Http\JsonResponse;

class EventRequestController extends Controller
{
    public function store(StoreEventRequestRequest $request): JsonResponse
    {
        $eventRequest = EventRequest::create($request->validated());

        return apiSuccess([
            'id' => $eventRequest->id,
            'event_title' => $eventRequest->event_title,
            'event_date' => $eventRequest->event_date->format('Y-m-d'),
            'status' => $eventRequest->status,
        ], 'event_request_submitted', 201);
    }
}
