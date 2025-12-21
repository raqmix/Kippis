<?php

namespace App\Integrations\Foodics\Exceptions;

class FoodicsServerErrorException extends FoodicsException
{
    public function __construct(string $message = 'Foodics server error')
    {
        parent::__construct('FOODICS_SERVER_ERROR', $message, 500);
    }
}

