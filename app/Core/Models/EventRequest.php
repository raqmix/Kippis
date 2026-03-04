<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;

class EventRequest extends Model
{
    protected $fillable = [
        'full_name',
        'email',
        'phone_country_code',
        'phone_number',
        'event_title',
        'event_type',
        'event_date',
        'start_time',
        'end_time',
        'number_of_guests',
        'city',
        'region',
        'address',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
        ];
    }

    public const EVENT_TYPES = [
        'corporate',
        'wedding',
        'birthday',
        'private_party',
        'reception',
        'other',
    ];
}
