<?php

namespace App\Core\Services;

use App\Core\Models\Customer;
use App\Core\Repositories\CustomerRepository;
use App\Core\Repositories\CustomerOtpRepository;
use App\Core\Repositories\LoyaltyWalletRepository;
use App\Helpers\FileHelper;
use App\Http\Exceptions\AccountNotVerifiedException;
use App\Http\Exceptions\InvalidOtpException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

class CustomerAuthService
{
    public function __construct(
        private CustomerRepository $customerRepository,
        private OtpService $otpService,
        private FileHelper $fileHelper,
        private LoyaltyWalletRepository $loyaltyWalletRepository
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

        // Award welcome bonus points
        $welcomeBonus = (int) config('core.loyalty.welcome_bonus_points', 100);
        if ($welcomeBonus > 0) {
            $wallet = $this->loyaltyWalletRepository->getOrCreateForCustomer($customer->id);
            $this->loyaltyWalletRepository->addPoints(
                $wallet,
                $welcomeBonus,
                'earned',
                'Welcome bonus points',
                'welcome_bonus',
                null
            );
        }

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
     * Lookup priority:
     *   1. Find by {provider}_id  — safest match, handles relay/changed emails
     *   2. Find by email          — links an existing manual/other-social account
     *   3. Create new account
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

            // Get user info from provider
            if ($provider === 'apple') {
                $claims = app(AppleTokenVerifier::class)->verify($token);

                $email      = $claims['email'] ?? null;
                $providerId = $claims['sub'];
                $socialName = null;
                $socialAvatar = null;
            } else {
                $claims = app(GoogleTokenVerifier::class)->verify($token);

                $email      = $claims['email'] ?? null;
                $providerId = $claims['sub'];
                $socialName = $claims['name'] ?? null;
                $socialAvatar = $claims['picture'] ?? null;
            }

            // For Apple, prioritize client-provided name on first login
            $appleName = null;
            if ($provider === 'apple' && $userData && isset($userData['name'])) {
                $appleName = trim((string) $userData['name']);
                if ($appleName !== '') {
                    $socialName = $appleName;
                }
            }

            $providerIdField = $provider . '_id';

            // ── Step 1: Look up by provider_id (most reliable) ──────────────
            $customer = $this->customerRepository->findByProviderId($provider, $providerId);

            // ── Step 2: Look up by email (account linking) ───────────────────
            if (!$customer && $email) {
                $customer = $this->customerRepository->findByEmail($email);
            }

            // ── Step 2.5: Look up by phone (handles same-number accounts) ────
            // Client may supply phone in userData (e.g. Apple signup flow where
            // the app already knows the user's phone from a previous step).
            $clientPhone = isset($userData['phone']) ? trim((string) $userData['phone']) : null;
            if (!$customer && $clientPhone !== null && $clientPhone !== '') {
                $customer = $this->customerRepository->findByPhone($clientPhone);
            }

            if ($customer) {
                // ── Existing customer — update any missing/changed fields ─────
                $updateData = [];

                // Link provider ID if not already set on this account
                if (empty($customer->$providerIdField)) {
                    $updateData[$providerIdField] = $providerId;
                }

                // Correct a stale provider ID (e.g. account was merged)
                if ($customer->$providerIdField !== $providerId) {
                    $updateData[$providerIdField] = $providerId;
                }

                // Backfill email if the account row somehow has no email yet
                if (!$customer->email && $email) {
                    $updateData['email'] = $email;
                }

                // Update name from Apple if account name is empty
                if ($provider === 'apple' && $appleName && in_array(trim((string) $customer->name), ['', 'User'])) {
                    $updateData['name'] = $appleName;
                }

                // Seed social avatar when no custom avatar is uploaded yet
                if ($socialAvatar && !$customer->avatar && !$customer->social_avatar) {
                    $updateData['social_avatar'] = $socialAvatar;
                }

                // Ensure social accounts are always verified
                if (!$customer->is_verified) {
                    $updateData['is_verified'] = true;
                }

                if (!empty($updateData)) {
                    $this->customerRepository->update($customer->id, $updateData);
                    $customer->refresh();
                }
            } else {
                // ── Step 3: No existing account — create new one ─────────────
                if (!$email) {
                    // Apple sometimes omits email on non-first-launch tokens;
                    // we cannot create an account without it.
                    throw new \App\Http\Exceptions\ApiException(
                        'EMAIL_REQUIRED',
                        'Email is required from social provider.',
                        400
                    );
                }

                $customerData = [
                    'name'         => $socialName ?: 'User',
                    'email'        => $email,
                    'phone'        => $clientPhone ?: null,
                    'country_code' => $userData['country_code'] ?? null,
                    'birthdate'    => $userData['birthdate'] ?? now()->subYears(18)->toDateString(),
                    'password'     => Hash::make(Str::random(32)),
                    'is_verified'  => true,
                    'social_avatar' => $socialAvatar,
                    $providerIdField => $providerId,
                ];

                try {
                    $customer = $this->customerRepository->create($customerData);
                } catch (\Illuminate\Database\QueryException $e) {
                    // Unique-constraint violation — find the conflicting row and link.
                    // Search with withTrashed=true because soft-deleted rows still
                    // occupy the unique index and would block an INSERT.
                    $customer = $this->customerRepository->findByProviderId($provider, $providerId)
                        ?? ($email ? $this->customerRepository->findByEmail($email, true) : null)
                        ?? ($clientPhone ? $this->customerRepository->findByPhone($clientPhone) : null);

                    if (!$customer) {
                        \Illuminate\Support\Facades\Log::error('Social login insert conflict unresolvable', [
                            'provider'  => $provider,
                            'email'     => $email,
                            'error'     => $e->getMessage(),
                        ]);
                        throw new \App\Http\Exceptions\ApiException(
                            'ACCOUNT_CONFLICT',
                            'Could not complete sign-in. Please contact support.',
                            409
                        );
                    }

                    // Restore soft-deleted account so the user can log in again.
                    if ($customer->trashed()) {
                        $customer->restore();
                        $customer->refresh();
                    }

                    // Link the provider and continue.
                    $this->customerRepository->update($customer->id, [
                        $providerIdField => $providerId,
                        'is_verified'    => true,
                    ]);
                    $customer->refresh();
                }
            }

            // Generate JWT token
            $jwtToken = $customer->generateToken();

            return [
                'customer' => $customer,
                'token'    => $jwtToken,
            ];
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

