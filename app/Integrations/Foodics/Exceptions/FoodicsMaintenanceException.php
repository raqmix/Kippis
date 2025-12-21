<?php

namespace App\Integrations\Foodics\Exceptions;

class FoodicsMaintenanceException extends FoodicsException
{
    public function __construct(string $message = 'Foodics is under maintenance')
    {
        parent::__construct('FOODICS_MAINTENANCE', $message, 503);
    }
}

