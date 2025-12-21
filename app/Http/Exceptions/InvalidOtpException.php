<?php

namespace App\Http\Exceptions;

/**
 * Invalid OTP Exception
 *
 * Thrown when OTP validation fails (invalid or expired).
 */
class InvalidOtpException extends ApiException
{
    public function __construct(string $message = 'Invalid or expired OTP.')
    {
        parent::__construct('OTP_INVALID', $message, 400);
    }
}
