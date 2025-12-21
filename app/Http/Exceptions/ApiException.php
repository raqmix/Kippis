<?php

namespace App\Http\Exceptions;

use Exception;

/**
 * Base API Exception
 *
 * All API exceptions extend this class to provide
 * consistent error code and message structure.
 */
class ApiException extends Exception
{
    protected string $errorCode;
    protected int $statusCode;

    public function __construct(string $errorCode, string $message, int $statusCode = 400)
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->statusCode = $statusCode;
    }

    /**
     * Get the error code.
     *
     * @return string
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Get the HTTP status code.
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
