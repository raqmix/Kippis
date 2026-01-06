<?php

namespace App\Core\Enums;

enum OrderStatus: string
{
    case RECEIVED = 'received';
    case MIXING = 'mixing';
    case READY = 'ready';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    /**
     * Get the translated label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::RECEIVED => __('system.received'),
            self::MIXING => __('system.mixing'),
            self::READY => __('system.ready'),
            self::COMPLETED => __('system.completed'),
            self::CANCELLED => __('system.cancelled'),
        };
    }

    /**
     * Get all status values as array.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

