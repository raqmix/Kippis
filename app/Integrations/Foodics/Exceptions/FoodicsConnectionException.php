<?php

namespace App\Integrations\Foodics\Exceptions;

class FoodicsConnectionException extends FoodicsException
{
    public function __construct(string $message = 'Connection failed')
    {
        parent::__construct('FOODICS_CONNECTION_FAILED', $message, 0);
    }
}

