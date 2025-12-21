<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Services\CustomerAuthService;
use App\Http\Controllers\Controller;
use App\Http\Exceptions\AccountNotVerifiedException;
use App\Http\Exceptions\ApiException;
use App\Http\Exceptions\InvalidOtpException;
use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\LoginCustomerRequest;
use App\Http\Requests\Api\V1\RegisterCustomerRequest;
use App\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Http\Resources\Api\V1\CustomerResource;
use App\Http\Requests\Api\V1\VerifyCustomerRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * @group Customer Authentication
 * 
 * APIs for customer registration, verification, login, and password management.
 */
class CustomerAuthController extends Controller
{
    public function __construct(
        private CustomerAuthService $authService
    ) {
    }

    /**
     * Register a new customer.
     * 
     * Register a new customer account. An OTP will be sent to the provided email for verification.
     *
     * @bodyParam name string required The customer's full name. Example: John Doe
     * @bodyParam email string required The customer's email address (must be unique). Example: john@example.com
     * @bodyParam phone string required The customer's phone number. Example: 1234567890
     * @bodyParam country_code string required The country code. Example: +1
     * @bodyParam birthdate date required The customer's birthdate (must be before today). Example: 1990-01-01
     * @bodyParam password string required The password (minimum 8 characters). Example: password123
     * @bodyParam password_confirmation string required Password confirmation. Example: password123
     * @bodyParam avatar file optional Customer avatar image (max 2MB).
     * 
     * @response 201 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "name": "John Doe",
     *     "email": "john@example.com",
     *     "phone": "1234567890",
     *     "country_code": "+1",
     *     "birthdate": "1990-01-01",
     *     "avatar": null,
     *     "is_verified": false,
     *     "created_at": "2025-12-21T10:00:00Z"
     *   },
     *   "message": "Registration successful. Please check your email for OTP verification."
     * }
     * 
     * @param RegisterCustomerRequest $request
     * @return JsonResponse
     */
    public function register(RegisterCustomerRequest $request): JsonResponse
    {
        try {
            $customer = $this->authService->register($request->validated());

            return apiSuccess(
                new CustomerResource($customer),
                'Registration successful. Please check your email for OTP verification.',
                201
            );
        } catch (\Exception $e) {
            Log::error('Customer registration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return apiError('SERVER_ERROR', 'Registration failed. Please try again later.', 500);
        }
    }

    /**
     * Verify customer account with OTP.
     * 
     * Verify the customer's email address using the OTP sent during registration.
     *
     * @bodyParam email string required The customer's email address. Example: john@example.com
     * @bodyParam otp string required The 6-digit OTP code. Example: 123456
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "name": "John Doe",
     *     "email": "john@example.com",
     *     "is_verified": true
     *   },
     *   "message": "Account verified successfully."
     * }
     * 
     * @param VerifyCustomerRequest $request
     * @return JsonResponse
     */
    public function verify(VerifyCustomerRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $customer = $this->authService->verify($validated['email'], $validated['otp']);

            return apiSuccess(
                new CustomerResource($customer),
                'Account verified successfully.'
            );
        } catch (InvalidOtpException $e) {
            return apiError($e->getErrorCode(), $e->getMessage(), $e->getStatusCode());
        } catch (ApiException $e) {
            return apiError($e->getErrorCode(), $e->getMessage(), $e->getStatusCode());
        } catch (\Exception $e) {
            Log::error('Customer verification failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return apiError('SERVER_ERROR', 'Verification failed. Please try again later.', 500);
        }
    }

    /**
     * Login customer.
     * 
     * Authenticate a customer and return a JWT token. The account must be verified.
     * 
     * The JWT token should be included in subsequent requests in the Authorization header:
     * `Authorization: Bearer {token}`
     *
     * @bodyParam email string required The customer's email address. Example: john@example.com
     * @bodyParam password string required The customer's password. Example: password123
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "customer": {
     *       "id": 1,
     *       "name": "John Doe",
     *       "email": "john@example.com"
     *     },
     *     "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
     *   },
     *   "message": "Login successful."
     * }
     * 
     * @param LoginCustomerRequest $request
     * @return JsonResponse
     */
    public function login(LoginCustomerRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $result = $this->authService->login($validated['email'], $validated['password']);

            return apiSuccess([
                'customer' => new CustomerResource($result['customer']),
                'token' => $result['token'],
            ], 'Login successful.');
        } catch (AccountNotVerifiedException $e) {
            return apiError($e->getErrorCode(), $e->getMessage(), $e->getStatusCode());
        } catch (ApiException $e) {
            return apiError($e->getErrorCode(), $e->getMessage(), $e->getStatusCode());
        } catch (\Exception $e) {
            Log::error('Customer login failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return apiError('SERVER_ERROR', 'Login failed. Please try again later.', 500);
        }
    }

    /**
     * Send forgot password OTP.
     * 
     * Request a password reset OTP to be sent to the customer's email.
     *
     * @bodyParam email string required The customer's email address. Example: john@example.com
     * 
     * @response 200 {
     *   "success": true,
     *   "message": "If the email exists, an OTP has been sent to your email address."
     * }
     * 
     * @param ForgotPasswordRequest $request
     * @return JsonResponse
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $this->authService->forgotPassword($request->validated()['email']);

            // Always return success for security (don't reveal if email exists)
            return apiSuccess(
                null,
                'If the email exists, an OTP has been sent to your email address.'
            );
        } catch (\Exception $e) {
            Log::error('Forgot password failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return apiError('SERVER_ERROR', 'Failed to send OTP. Please try again later.', 500);
        }
    }

    /**
     * Reset password with OTP.
     * 
     * Reset the customer's password using the OTP sent via forgot password.
     *
     * @bodyParam email string required The customer's email address. Example: john@example.com
     * @bodyParam otp string required The 6-digit OTP code. Example: 123456
     * @bodyParam password string required The new password (minimum 8 characters). Example: newpassword123
     * @bodyParam password_confirmation string required Password confirmation. Example: newpassword123
     * 
     * @response 200 {
     *   "success": true,
     *   "message": "Password reset successfully. You can now login with your new password."
     * }
     * 
     * @param ResetPasswordRequest $request
     * @return JsonResponse
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $this->authService->resetPassword(
                $validated['email'],
                $validated['otp'],
                $validated['password']
            );

            return apiSuccess(
                null,
                'Password reset successfully. You can now login with your new password.'
            );
        } catch (InvalidOtpException $e) {
            return apiError($e->getErrorCode(), $e->getMessage(), $e->getStatusCode());
        } catch (ApiException $e) {
            return apiError($e->getErrorCode(), $e->getMessage(), $e->getStatusCode());
        } catch (\Exception $e) {
            Log::error('Password reset failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return apiError('SERVER_ERROR', 'Password reset failed. Please try again later.', 500);
        }
    }

    /**
     * Delete customer account.
     * 
     * Permanently delete the authenticated customer's account. This will invalidate the JWT token and soft delete the account.
     * 
     * @authenticated
     * 
     * @header Authorization Bearer {token} JWT token obtained from login
     * 
     * @response 200 {
     *   "success": true,
     *   "message": "Account deleted successfully."
     * }
     * 
     * @response 401 {
     *   "success": false,
     *   "error": {
     *     "code": "UNAUTHORIZED",
     *     "message": "Unauthorized. Please login first."
     *   }
     * }
     * 
     * @return JsonResponse
     */
    public function deleteAccount(): JsonResponse
    {
        try {
            $customer = auth('api')->user();
            
            if (!$customer) {
                return apiError('UNAUTHORIZED', 'Unauthorized. Please login first.', 401);
            }

            $this->authService->deleteAccount($customer->id);

            return apiSuccess(
                null,
                'Account deleted successfully.'
            );
        } catch (ApiException $e) {
            return apiError($e->getErrorCode(), $e->getMessage(), $e->getStatusCode());
        } catch (\Exception $e) {
            Log::error('Account deletion failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return apiError('SERVER_ERROR', 'Account deletion failed. Please try again later.', 500);
        }
    }

    /**
     * Get authenticated customer data.
     * 
     * Returns the authenticated customer's profile information.
     * 
     * @authenticated
     * 
     * @header Authorization Bearer {token} JWT token obtained from login
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "name": "John Doe",
     *     "email": "john@example.com",
     *     "phone": "1234567890",
     *     "country_code": "+1",
     *     "birthdate": "1990-01-01",
     *     "avatar": "http://localhost/storage/customers/avatar.jpg",
     *     "foodics_customer_id": null,
     *     "is_verified": true,
     *     "created_at": "2023-01-01 12:00:00",
     *     "updated_at": "2023-01-01 12:00:00"
     *   }
     * }
     * 
     * @response 401 {
     *   "success": false,
     *   "error": {
     *     "code": "UNAUTHORIZED",
     *     "message": "Unauthorized. Please login first."
     *   }
     * }
     * 
     * @response 404 {
     *   "success": false,
     *   "error": {
     *     "code": "CUSTOMER_NOT_FOUND",
     *     "message": "Customer not found."
     *   }
     * }
     * 
     * @return JsonResponse
     */
    public function me(): JsonResponse
    {
        try {
            $customer = auth('api')->user();
            
            if (!$customer) {
                return apiError('UNAUTHORIZED', 'Unauthorized. Please login first.', 401);
            }

            $customerData = $this->authService->getCustomer($customer->id);

            return apiSuccess(
                new CustomerResource($customerData)
            );
        } catch (ApiException $e) {
            return apiError($e->getErrorCode(), $e->getMessage(), $e->getStatusCode());
        } catch (\Exception $e) {
            Log::error('Get customer data failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return apiError('SERVER_ERROR', 'Failed to retrieve customer data. Please try again later.', 500);
        }
    }

    /**
     * Logout customer.
     * 
     * Invalidates the current JWT token, effectively logging out the customer.
     * 
     * @authenticated
     * 
     * @header Authorization Bearer {token} JWT token obtained from login
     * 
     * @response 200 {
     *   "success": true,
     *   "message": "Logged out successfully."
     * }
     * 
     * @response 401 {
     *   "success": false,
     *   "error": {
     *     "code": "UNAUTHORIZED",
     *     "message": "Unauthorized. Please login first."
     *   }
     * }
     * 
     * @return JsonResponse
     */
    public function logout(): JsonResponse
    {
        try {
            $customer = auth('api')->user();
            
            if (!$customer) {
                return apiError('UNAUTHORIZED', 'Unauthorized. Please login first.', 401);
            }

            $this->authService->logout($customer->id);

            return apiSuccess(
                null,
                'Logged out successfully.'
            );
        } catch (\Exception $e) {
            Log::error('Logout failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return apiError('SERVER_ERROR', 'Logout failed. Please try again later.', 500);
        }
    }

    /**
     * Refresh JWT token.
     * 
     * Generates a new JWT token for the authenticated customer, invalidating the old one.
     * This is useful for extending the session without requiring the customer to login again.
     * 
     * @authenticated
     * 
     * @header Authorization Bearer {token} JWT token obtained from login
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
     *   },
     *   "message": "Token refreshed successfully."
     * }
     * 
     * @response 401 {
     *   "success": false,
     *   "error": {
     *     "code": "UNAUTHORIZED",
     *     "message": "Unauthorized. Please login first."
     *   }
     * }
     * 
     * @return JsonResponse
     */
    public function refreshToken(): JsonResponse
    {
        try {
            $customer = auth('api')->user();
            
            if (!$customer) {
                return apiError('UNAUTHORIZED', 'Unauthorized. Please login first.', 401);
            }

            $newToken = $this->authService->refreshToken($customer->id);

            return apiSuccess(
                ['token' => $newToken],
                'Token refreshed successfully.'
            );
        } catch (\Exception $e) {
            Log::error('Token refresh failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return apiError('SERVER_ERROR', 'Token refresh failed. Please try again later.', 500);
        }
    }
}
