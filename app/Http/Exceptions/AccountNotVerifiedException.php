<?php

namespace App\Http\Exceptions;

/**
 * Account Not Verified Exception
 *
 * Thrown when attempting to login with an unverified account.
 */
class AccountNotVerifiedException extends ApiException
{
    public function __construct(string $message = null)
    {
        $message = $message ?? __('api.account_not_verified');
        parent::__construct('ACCOUNT_NOT_VERIFIED', $message, 403);
    }
}

