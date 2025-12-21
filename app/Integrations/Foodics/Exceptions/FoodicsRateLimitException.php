<?php

namespace App\Integrations\Foodics\Exceptions;

class FoodicsRateLimitException extends FoodicsException
{
    public function __construct(string $message = 'Rate limit exceeded')
    {
        parent::__construct('FOODICS_RATE_LIMIT', $message, 429);
    }
}

