<?php

namespace App\Http\Exceptions;

/**
 * Invalid OTP Exception
 *
 * Thrown when OTP validation fails (invalid or expired).
 */
class InvalidOtpException extends ApiException
{
    public function __construct(string $message = null)
    {
        $message = $message ?? __('api.otp_invalid');
        parent::__construct('OTP_INVALID', $message, 400);
    }
}
