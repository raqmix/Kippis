<?php

namespace App\Http\Exceptions;

/**
 * Account Not Verified Exception
 *
 * Thrown when attempting to login with an unverified account.
 */
class AccountNotVerifiedException extends ApiException
{
    public function __construct(string $message = 'Account is not verified. Please verify your email first.')
    {
        parent::__construct('ACCOUNT_NOT_VERIFIED', $message, 403);
    }
}

