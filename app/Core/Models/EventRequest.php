<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EventRequest extends Model
{
    protected $fillable = [
        'reference_number',
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

    /**
     * Auto-assign a unique, customer-readable reference on create
     * (e.g. `EVT-A8F3D2`). The customer quotes this number when calling
     * the events team, and we surface it on the success screen.
     */
    protected static function booted(): void
    {
        static::creating(function (EventRequest $eventRequest) {
            if (empty($eventRequest->reference_number)) {
                $eventRequest->reference_number = self::generateReferenceNumber();
            }
        });
    }

    public static function generateReferenceNumber(): string
    {
        do {
            $candidate = 'EVT-' . strtoupper(Str::random(6));
        } while (self::where('reference_number', $candidate)->exists());

        return $candidate;
    }
}
