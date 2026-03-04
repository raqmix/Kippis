<?php

namespace App\Http\Requests\Api\V1;

use App\Core\Models\EventRequest;
use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone_country_code' => ['required', 'string', 'max:10'],
            'phone_number' => ['required', 'string', 'max:20'],
            'event_title' => ['required', 'string', 'max:255'],
            'event_type' => ['required', 'string', 'in:' . implode(',', EventRequest::EVENT_TYPES)],
            'event_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'number_of_guests' => ['required', 'integer', 'min:1', 'max:10000'],
            'city' => ['required', 'string', 'max:100'],
            'region' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:500'],
        ];
    }
}
