<?php

namespace App\Http\Requests\Api\V1;

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
            // Mobile lets the user pick a preset (corporate, wedding, …) or
            // type a custom label when they choose "Other". Both cases land
            // in `event_type` as free-form text, so we no longer constrain
            // to the preset enum here. The EVENT_TYPES const is still the
            // source of truth for the admin's filter dropdown.
            'event_type' => ['required', 'string', 'max:100'],
            'event_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'number_of_guests' => ['required', 'integer', 'min:1', 'max:10000'],
            'city' => ['required', 'string', 'max:100'],
            'region' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
