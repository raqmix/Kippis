<?php

namespace App\Integrations\Foodics\Exceptions;

use App\Http\Exceptions\ApiException;

class FoodicsException extends ApiException
{
    public function __construct(string $errorCode, string $message, int $statusCode = 400)
    {
        parent::__construct($errorCode, $message, $statusCode);
    }
}

