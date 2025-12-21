<?php

namespace App\Integrations\Foodics\Exceptions;

class FoodicsForbiddenException extends FoodicsException
{
    public function __construct(string $message = 'Access forbidden')
    {
        parent::__construct('FOODICS_FORBIDDEN', $message, 403);
    }
}

