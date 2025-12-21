<?php

namespace App\Core\Repositories;

use App\Core\Models\CustomerOtp;
use Carbon\Carbon;

class CustomerOtpRepository
{
    /**
     * Create a new OTP record.
     *
     * @param array $data
     * @return CustomerOtp
     */
    public function create(array $data): CustomerOtp
    {
        return CustomerOtp::create($data);
    }

    /**
     * Find OTP by email, OTP code, and type.
     *
     * @param string $email
     * @param string $otp
     * @param string $type
     * @return CustomerOtp|null
     */
    public function findByEmailAndOtp(string $email, string $otp, string $type): ?CustomerOtp
    {
        return CustomerOtp::where('email', $email)
            ->where('otp', $otp)
            ->where('type', $type)
            ->whereNull('verified_at')
            ->first();
    }

    /**
     * Delete OTPs by email and type.
     *
     * @param string $email
     * @param string $type
     * @return int Number of deleted records
     */
    public function deleteByEmail(string $email, string $type): int
    {
        return CustomerOtp::where('email', $email)
            ->where('type', $type)
            ->delete();
    }

    /**
     * Delete expired OTPs.
     *
     * @return int Number of deleted records
     */
    public function deleteExpired(): int
    {
        return CustomerOtp::where('expires_at', '<', Carbon::now())->delete();
    }
}
