<?php

namespace App\Integrations\Foodics\Exceptions;

class FoodicsTimeoutException extends FoodicsException
{
    public function __construct(string $message = 'Request timeout')
    {
        parent::__construct('FOODICS_TIMEOUT', $message, 408);
    }
}

