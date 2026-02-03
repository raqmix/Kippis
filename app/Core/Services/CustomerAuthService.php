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
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

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
        $this->otpService->sendOtp($customer->email, $otpRecord->otp, 'verification');

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
        $this->otpService->sendOtp($customer->email, $otpRecord->otp, 'password_reset');
    }

    /**
     * Resend OTP to customer.
     *
     * @param string $email
     * @param string $type OTP type (verification, password_reset)
     * @return void
     * @throws ApiException
     */
    public function resendOtp(string $email, string $type = 'verification'): void
    {
        $customer = $this->customerRepository->findByEmail($email);

        if (!$customer) {
            // Don't reveal if email exists for security
            return;
        }

        // Generate and send new OTP
        $otpRecord = $this->otpService->createOtp($customer, $type);
        $this->otpService->sendOtp($customer->email, $otpRecord->otp, $type);
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
        try {
            \Tymon\JWTAuth\Facades\JWTAuth::invalidate(\Tymon\JWTAuth\Facades\JWTAuth::getToken());
        } catch (\Exception $e) {
            // Token already invalid or missing, that's fine
        }
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
            return \Tymon\JWTAuth\Facades\JWTAuth::refresh();
        } catch (TokenBlacklistedException $e) {
            throw new \App\Http\Exceptions\ApiException('TOKEN_BLACKLISTED', 'Token has been blacklisted.', 401);
        } catch (TokenExpiredException $e) {
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
        try {
            \Tymon\JWTAuth\Facades\JWTAuth::invalidate(\Tymon\JWTAuth\Facades\JWTAuth::getToken());
        } catch (\Exception $e) {
            // Token already invalid or missing, that's fine
        }

        // Soft delete the customer
        $customer->delete();
    }

    /**
     * Handle social login (Google or Apple).
     *
     * @param string $provider ('google' or 'apple')
     * @param string $token (access_token for Google, id_token for Apple)
     * @param array|null $userData (Optional user data from client, used for Apple name on first login)
     * @return array ['customer' => Customer, 'token' => string]
     * @throws \App\Http\Exceptions\ApiException
     */
    public function socialLogin(string $provider, string $token, ?array $userData = null): array
    {
        try {
            // Validate provider
            if (!in_array($provider, ['google', 'apple'])) {
                throw new \App\Http\Exceptions\ApiException(
                    'INVALID_PROVIDER',
                    'Provider must be either google or apple.',
                    400
                );
            }

            // Get user info from provider using Socialite
            /** @var \Laravel\Socialite\Two\User $socialUser */
            $socialUser = Socialite::driver($provider)
                ->stateless() // @phpstan-ignore-next-line
                ->userFromToken($token);

            // Extract social user data
            $email = $socialUser->getEmail();
            $providerId = $socialUser->getId();
            $socialName = $socialUser->getName();
            $socialAvatar = $socialUser->getAvatar();

            // For Apple, prioritize client-provided name on first login
            if ($provider === 'apple' && $userData && isset($userData['name'])) {
                $socialName = $userData['name'];
            }

            if (!$email) {
                throw new \App\Http\Exceptions\ApiException(
                    'EMAIL_REQUIRED',
                    'Email is required from social provider.',
                    400
                );
            }

            // Find existing customer by email
            $customer = $this->customerRepository->findByEmail($email);

            if ($customer) {
                // Customer exists - link provider ID if not already linked
                $providerIdField = $provider . '_id';
                $providerTokenField = $provider . '_refresh_token';

                $updateData = [];

                // Update provider ID if not set or different
                if (!$customer->$providerIdField || $customer->$providerIdField !== $providerId) {
                    $updateData[$providerIdField] = $providerId;
                }

                // Update social avatar if available
                if ($socialAvatar && !$customer->avatar) {
                    $updateData['social_avatar'] = $socialAvatar;
                }

                // Update customer if there are changes
                if (!empty($updateData)) {
                    $this->customerRepository->update($customer->id, $updateData);
                    $customer->refresh();
                }
            } else {
                // Customer doesn't exist - create new one
                $customerData = [
                    'name' => $socialName ?: 'User',
                    'email' => $email,
                    'password' => Hash::make(Str::random(32)), // Random placeholder password
                    'is_verified' => true, // Auto-verify social logins
                    'social_avatar' => $socialAvatar,
                ];

                // Set provider ID
                $customerData[$provider . '_id'] = $providerId;

                $customer = $this->customerRepository->create($customerData);
            }

            // Generate JWT token
            $jwtToken = $customer->generateToken();

            return [
                'customer' => $customer,
                'token' => $jwtToken,
            ];
        } catch (\Laravel\Socialite\Two\InvalidStateException $e) {
            throw new \App\Http\Exceptions\ApiException(
                'INVALID_TOKEN',
                'Invalid or expired social token.',
                401
            );
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            throw new \App\Http\Exceptions\ApiException(
                'SOCIAL_AUTH_FAILED',
                'Failed to authenticate with social provider.',
                401
            );
        } catch (\App\Http\Exceptions\ApiException $e) {
            // Re-throw our custom exceptions
            throw $e;
        } catch (\Exception $e) {
            throw new \App\Http\Exceptions\ApiException(
                'SOCIAL_LOGIN_ERROR',
                'Social login failed: ' . $e->getMessage(),
                500
            );
        }
    }
}

