<?php

namespace App\Integrations\Foodics\Exceptions;

class FoodicsNotFoundException extends FoodicsException
{
    public function __construct(string $message = 'Resource not found')
    {
        parent::__construct('FOODICS_NOT_FOUND', $message, 404);
    }
}

