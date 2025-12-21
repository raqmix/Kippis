<?php

namespace App\Core\Services;

use App\Core\Models\Customer;
use App\Core\Repositories\CustomerRepository;
use App\Core\Repositories\CustomerOtpRepository;
use App\Helpers\FileHelper;
use App\Http\Exceptions\AccountNotVerifiedException;
use App\Http\Exceptions\InvalidOtpException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class CustomerAuthService
{
    public function __construct(
        private CustomerRepository $customerRepository,
        private OtpService $otpService,
        private FileHelper $fileHelper
    ) {
    }

    /**
     * Register a new customer.
     *
     * @param array $data
     * @return Customer
     */
    public function register(array $data): Customer
    {
        // Upload avatar if provided
        if (isset($data['avatar']) && $data['avatar']) {
            $data['avatar'] = $this->fileHelper->upload($data['avatar'], 'customers');
        } else {
            unset($data['avatar']);
        }

        // Remove password_confirmation from data
        unset($data['password_confirmation']);

        // Create customer
        $customer = $this->customerRepository->create($data);

        // Generate and send OTP
        $otpRecord = $this->otpService->createOtp($customer, 'verification');
        $this->otpService->sendOtp($customer->email, $otpRecord->otp);

        return $customer;
    }

    /**
     * Verify customer account with OTP.
     *
     * @param string $email
     * @param string $otp
     * @return Customer
     * @throws InvalidOtpException
     */
    public function verify(string $email, string $otp): Customer
    {
        // Validate OTP
        $otpRecord = $this->otpService->validateOtp($email, $otp, 'verification');

        // Get customer
        $customer = $this->customerRepository->findByEmail($email);

        if (!$customer) {
            throw new \App\Http\Exceptions\ApiException('CUSTOMER_NOT_FOUND', __('api.customer_not_found'), 404);
        }

        // Mark customer as verified
        $customer->markAsVerified();

        // Mark OTP as verified and delete it
        $otpRecord->markAsVerified();
        app(CustomerOtpRepository::class)->deleteByEmail($email, 'verification');

        return $customer;
    }

    /**
     * Login customer.
     *
     * @param string $email
     * @param string $password
     * @return array
     * @throws AccountNotVerifiedException
     */
    public function login(string $email, string $password): array
    {
        $customer = $this->customerRepository->findByEmail($email);

        if (!$customer || !Hash::check($password, $customer->password)) {
            throw new \App\Http\Exceptions\ApiException('INVALID_CREDENTIALS', __('api.invalid_credentials'), 400);
        }

        if (!$customer->is_verified) {
            throw new AccountNotVerifiedException();
        }

        // Generate API token
        $token = $customer->generateToken();

        return [
            'customer' => $customer,
            'token' => $token,
        ];
    }

    /**
     * Send forgot password OTP.
     *
     * @param string $email
     * @return void
     */
    public function forgotPassword(string $email): void
    {
        $customer = $this->customerRepository->findByEmail($email);

        if (!$customer) {
            // Don't reveal if email exists for security
            return;
        }

        // Generate and send OTP
        $otpRecord = $this->otpService->createOtp($customer, 'password_reset');
        $this->otpService->sendOtp($customer->email, $otpRecord->otp);
    }

    /**
     * Reset password with OTP.
     *
     * @param string $email
     * @param string $otp
     * @param string $password
     * @return void
     * @throws InvalidOtpException
     */
    public function resetPassword(string $email, string $otp, string $password): void
    {
        // Validate OTP
        $otpRecord = $this->otpService->validateOtp($email, $otp, 'password_reset');

        // Get customer
        $customer = $this->customerRepository->findByEmail($email);

        if (!$customer) {
            throw new \App\Http\Exceptions\ApiException('CUSTOMER_NOT_FOUND', __('api.customer_not_found'), 404);
        }

        // Update password
        $this->customerRepository->updatePassword($customer->id, $password);

        // Mark OTP as verified and delete it
        $otpRecord->markAsVerified();
        app(CustomerOtpRepository::class)->deleteByEmail($email, 'password_reset');
    }

    /**
     * Get authenticated customer data.
     *
     * @param int $customerId
     * @return Customer
     * @throws ApiException
     */
    public function getCustomer(int $customerId): Customer
    {
        $customer = $this->customerRepository->findById($customerId);

        if (!$customer) {
            throw new \App\Http\Exceptions\ApiException('CUSTOMER_NOT_FOUND', 'Customer not found.', 404);
        }

        return $customer;
    }

    /**
     * Logout customer (invalidate JWT token).
     *
     * @param int $customerId
     * @return void
     */
    public function logout(int $customerId): void
    {
        // Invalidate JWT token by logging out
        auth('api')->logout();
    }

    /**
     * Refresh JWT token for customer.
     *
     * @param int $customerId
     * @return string
     * @throws ApiException
     */
    public function refreshToken(int $customerId): string
    {
        try {
            // Refresh the JWT token (this invalidates the old token)
            return auth('api')->refresh();
        } catch (\Tymon\JWTAuth\Exceptions\TokenBlacklistedException $e) {
            throw new \App\Http\Exceptions\ApiException('TOKEN_BLACKLISTED', 'Token has been blacklisted.', 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            throw new \App\Http\Exceptions\ApiException('TOKEN_EXPIRED', 'Token has expired.', 401);
        } catch (\Exception $e) {
            throw new \App\Http\Exceptions\ApiException('TOKEN_REFRESH_FAILED', 'Failed to refresh token.', 500);
        }
    }

    /**
     * Delete customer account (soft delete).
     *
     * @param int $customerId
     * @return void
     * @throws ApiException
     */
    public function deleteAccount(int $customerId): void
    {
        $customer = $this->customerRepository->findById($customerId);

        if (!$customer) {
            throw new \App\Http\Exceptions\ApiException('CUSTOMER_NOT_FOUND', __('api.customer_not_found'), 404);
        }

        // Invalidate JWT token (logout)
        auth('api')->logout();

        // Soft delete the customer
        $customer->delete();
    }
}

