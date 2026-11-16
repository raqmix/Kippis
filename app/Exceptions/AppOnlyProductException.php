<?php

namespace App\Exceptions;

use InvalidArgumentException;

/** A secret-menu (app-only) product was added from a non-mobile platform. */
class AppOnlyProductException extends InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct('This product is only available in the Kippis app.');
    }
}
