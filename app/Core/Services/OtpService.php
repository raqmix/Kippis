<?php

namespace App\Core\Services;

use App\Core\Models\Customer;
use App\Core\Models\CustomerOtp;
use App\Core\Repositories\CustomerOtpRepository;
use App\Mail\OtpMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    public function __construct(
        private CustomerOtpRepository $otpRepository
    ) {
    }

    /**
     * Toggle for the static '111111' shortcut. Set OTP_STATIC_CODE=true in
     * the env to bypass real OTP generation/sending — useful in local dev
     * without an SMTP setup. Defaults to false so production is safe.
     */
    private function staticOtpEnabled(): bool
    {
        return (bool) env('OTP_STATIC_CODE', false);
    }

    /**
     * Generate a 6-digit OTP. When OTP_STATIC_CODE=true, returns '111111'.
     */
    public function generateOtp(): string
    {
        if ($this->staticOtpEnabled()) {
            return '111111';
        }

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
     * In dev environment, accepts '111111' as valid OTP.
     *
     * @param string $email
     * @param string $otp
     * @param string $type
     * @return CustomerOtp
     * @throws \App\Http\Exceptions\InvalidOtpException
     */
    public function validateOtp(string $email, string $otp, string $type): CustomerOtp
    {
        if ($this->staticOtpEnabled() && $otp === '111111') {
            // First try to find existing OTP record
            $otpRecord = $this->otpRepository->findByEmailAndType($email, $type);
            
            if ($otpRecord) {
                // If record exists, check if it's not expired
                if (!$otpRecord->isExpired()) {
                    return $otpRecord;
                }
            }
            
            // If no valid record exists, verify customer exists and create/update OTP record
            $customer = Customer::where('email', $email)->first();
            if (!$customer) {
                throw new \App\Http\Exceptions\InvalidOtpException();
            }
            
            // Delete any expired OTPs and create a new one
            $this->otpRepository->deleteByEmail($email, $type);
            $otpRecord = $this->otpRepository->create([
                'customer_id' => $customer->id,
                'email' => $email,
                'otp' => '111111',
                'type' => $type,
                'expires_at' => now()->addMinutes(5),
            ]);
            
            return $otpRecord;
        }

        // Normal OTP validation
        $otpRecord = $this->otpRepository->findByEmailAndOtp($email, $otp, $type);

        if (!$otpRecord) {
            throw new \App\Http\Exceptions\InvalidOtpException();
        }

        if ($otpRecord->isExpired()) {
            throw new \App\Http\Exceptions\InvalidOtpException();
        }

        return $otpRecord;
    }

    /**
     * Send OTP to customer via email.
     * In dev environment, skips email sending and just logs the static OTP.
     *
     * @param string $email
     * @param string $otp
     * @param string|null $type OTP type (verification, password_reset)
     * @return void
     */
    public function sendOtp(string $email, string $otp, ?string $type = null): void
    {
        // When the static-code flag is on, skip email sending entirely and
        // log the OTP — but only outside production. With LOG_LEVEL=debug
        // and any log shipper / Filament log viewer, plaintext OTPs in the
        // log line are readable within the 5-min validity window (#35).
        if ($this->staticOtpEnabled()) {
            if (app()->environment('local', 'testing')) {
                Log::info('OTP (static-code mode - email skipped)', [
                    'email' => $email,
                    'otp' => $otp,
                    'type' => $type,
                    'timestamp' => now(),
                ]);
            }
            return;
        }

        // In production, send OTP via email. The send path no longer
        // logs the OTP — only the email + type so we can still
        // correlate failures without leaking the code.
        try {
            Mail::to($email)->send(new OtpMail($otp, $type));

            Log::info('OTP sent via email', [
                'email' => $email,
                'type' => $type,
                'timestamp' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send OTP email', [
                'email' => $email,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
