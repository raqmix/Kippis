<?php

namespace App\Integrations\Foodics\Exceptions;

class FoodicsUnauthorizedException extends FoodicsException
{
    public function __construct(string $message = 'Foodics authentication failed')
    {
        parent::__construct('FOODICS_UNAUTHORIZED', $message, 401);
    }
}

