<?php

namespace App\Integrations\Foodics\Exceptions;

class FoodicsValidationException extends FoodicsException
{
    public function __construct(string $message = 'Validation error', public ?array $errors = null)
    {
        parent::__construct('FOODICS_VALIDATION_ERROR', $message, 422);
    }
}

