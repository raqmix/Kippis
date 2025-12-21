<?php

namespace App\Core\Services;

use App\Core\Models\Customer;
use App\Core\Models\CustomerOtp;
use App\Core\Repositories\CustomerOtpRepository;
use Illuminate\Support\Facades\Log;

class OtpService
{
    public function __construct(
        private CustomerOtpRepository $otpRepository
    ) {
    }

    /**
     * Generate a 6-digit OTP.
     *
     * @return string
     */
    public function generateOtp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Create and save OTP for customer.
     *
     * @param Customer $customer
     * @param string $type
     * @param int $expiryMinutes
     * @return CustomerOtp
     */
    public function createOtp(Customer $customer, string $type, int $expiryMinutes = 5): CustomerOtp
    {
        // Delete any existing OTPs of the same type for this email
        $this->otpRepository->deleteByEmail($customer->email, $type);

        $otp = $this->generateOtp();

        return $this->otpRepository->create([
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'otp' => $otp,
            'type' => $type,
            'expires_at' => now()->addMinutes($expiryMinutes),
        ]);
    }

    /**
     * Validate OTP.
     *
     * @param string $email
     * @param string $otp
     * @param string $type
     * @return CustomerOtp
     * @throws \App\Http\Exceptions\InvalidOtpException
     */
    public function validateOtp(string $email, string $otp, string $type): CustomerOtp
    {
        $otpRecord = $this->otpRepository->findByEmailAndOtp($email, $otp, $type);

        if (!$otpRecord) {
            throw new \App\Http\Exceptions\InvalidOtpException('Invalid OTP code.');
        }

        if ($otpRecord->isExpired()) {
            throw new \App\Http\Exceptions\InvalidOtpException('OTP has expired. Please request a new one.');
        }

        return $otpRecord;
    }

    /**
     * Send OTP to customer (mock implementation).
     *
     * @param string $email
     * @param string $otp
     * @return void
     */
    public function sendOtp(string $email, string $otp): void
    {
        // Mock implementation - log for now
        // In production, integrate with SMS/Email service
        Log::info('OTP sent', [
            'email' => $email,
            'otp' => $otp,
            'timestamp' => now(),
        ]);

        // TODO: Integrate with actual SMS/Email service
        // Example: $smsService->send($phone, "Your OTP is: {$otp}");
    }
}
