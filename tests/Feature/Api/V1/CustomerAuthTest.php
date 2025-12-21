<?php

namespace Tests\Feature\Api\V1;

use App\Core\Models\Customer;
use App\Core\Models\CustomerOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class CustomerAuthTest extends TestCase
{
    use RefreshDatabase;

    protected string $baseUrl = '/api/v1/customers';

    /**
     * Test customer registration with valid data.
     */
    public function test_customer_can_register_with_valid_data(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'country_code' => '+1',
            'birthdate' => '1990-01-01',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson("{$this->baseUrl}/register", $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'phone',
                    'country_code',
                    'birthdate',
                    'is_verified',
                ],
                'message',
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'is_verified' => false,
                ],
            ]);

        $this->assertDatabaseHas('customers', [
            'email' => 'john@example.com',
            'is_verified' => false,
        ]);

        // Check that OTP was created
        $this->assertDatabaseHas('customer_otps', [
            'email' => 'john@example.com',
            'type' => 'verification',
        ]);
    }

    /**
     * Test customer registration with validation errors.
     */
    public function test_customer_registration_fails_with_invalid_data(): void
    {
        $data = [
            'name' => '',
            'email' => 'invalid-email',
            'phone' => '',
            'country_code' => '',
            'birthdate' => '2025-01-01', // Future date
            'password' => '123', // Too short
            'password_confirmation' => '456', // Doesn't match
        ];

        $response = $this->postJson("{$this->baseUrl}/register", $data);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'error' => [
                    'code',
                    'message',
                    'errors',
                ],
            ])
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                ],
            ]);
    }

    /**
     * Test customer registration with duplicate email.
     */
    public function test_customer_registration_fails_with_duplicate_email(): void
    {
        $existingCustomer = Customer::factory()->create(['email' => 'existing@example.com']);

        $data = [
            'name' => 'John Doe',
            'email' => 'existing@example.com',
            'phone' => '1234567890',
            'country_code' => '+1',
            'birthdate' => '1990-01-01',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson("{$this->baseUrl}/register", $data);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                ],
            ]);
    }

    /**
     * Test customer verification with valid OTP.
     */
    public function test_customer_can_verify_account_with_valid_otp(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'john@example.com',
            'is_verified' => false,
        ]);

        $otp = CustomerOtp::factory()->create([
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'otp' => '123456',
            'type' => 'verification',
            'expires_at' => now()->addMinutes(5),
        ]);

        $data = [
            'email' => 'john@example.com',
            'otp' => '123456',
        ];

        $response = $this->postJson("{$this->baseUrl}/verify", $data);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'is_verified',
                ],
                'message',
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_verified' => true,
                ],
            ]);

        $this->assertDatabaseHas('customers', [
            'email' => 'john@example.com',
            'is_verified' => true,
        ]);
    }

    /**
     * Test customer verification fails with invalid OTP.
     */
    public function test_customer_verification_fails_with_invalid_otp(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'john@example.com',
            'is_verified' => false,
        ]);

        CustomerOtp::factory()->create([
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'otp' => '123456',
            'type' => 'verification',
        ]);

        $data = [
            'email' => 'john@example.com',
            'otp' => '999999', // Wrong OTP
        ];

        $response = $this->postJson("{$this->baseUrl}/verify", $data);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'OTP_INVALID',
                ],
            ]);
    }

    /**
     * Test customer verification fails with expired OTP.
     */
    public function test_customer_verification_fails_with_expired_otp(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'john@example.com',
            'is_verified' => false,
        ]);

        $otp = CustomerOtp::factory()->expired()->create([
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'otp' => '123456',
            'type' => 'verification',
        ]);

        $data = [
            'email' => 'john@example.com',
            'otp' => '123456',
        ];

        $response = $this->postJson("{$this->baseUrl}/verify", $data);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'OTP_INVALID',
                ],
            ]);
    }

    /**
     * Test customer login with valid credentials.
     */
    public function test_customer_can_login_with_valid_credentials(): void
    {
        $customer = Customer::factory()->verified()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $data = [
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        $response = $this->postJson("{$this->baseUrl}/login", $data);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'customer' => [
                        'id',
                        'name',
                        'email',
                    ],
                    'token',
                ],
                'message',
            ])
            ->assertJson([
                'success' => true,
            ]);

        $responseData = $response->json('data');
        $this->assertNotEmpty($responseData['token']);
        $this->assertEquals($customer->id, $responseData['customer']['id']);
    }

    /**
     * Test customer login fails with invalid credentials.
     */
    public function test_customer_login_fails_with_invalid_credentials(): void
    {
        $customer = Customer::factory()->verified()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $data = [
            'email' => 'john@example.com',
            'password' => 'wrongpassword',
        ];

        $response = $this->postJson("{$this->baseUrl}/login", $data);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_CREDENTIALS',
                ],
            ]);
    }

    /**
     * Test customer login fails when account is not verified.
     */
    public function test_customer_login_fails_when_account_not_verified(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
            'is_verified' => false,
        ]);

        $data = [
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        $response = $this->postJson("{$this->baseUrl}/login", $data);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'ACCOUNT_NOT_VERIFIED',
                ],
            ]);
    }

    /**
     * Test forgot password sends OTP.
     */
    public function test_forgot_password_sends_otp(): void
    {
        $customer = Customer::factory()->verified()->create([
            'email' => 'john@example.com',
        ]);

        $data = [
            'email' => 'john@example.com',
        ];

        $response = $this->postJson("{$this->baseUrl}/forgot-password", $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Check that OTP was created
        $this->assertDatabaseHas('customer_otps', [
            'email' => 'john@example.com',
            'type' => 'password_reset',
        ]);
    }

    /**
     * Test forgot password validation requires email to exist.
     */
    public function test_forgot_password_requires_valid_email(): void
    {
        $data = [
            'email' => 'nonexistent@example.com',
        ];

        $response = $this->postJson("{$this->baseUrl}/forgot-password", $data);

        // Validation requires email to exist
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                ],
            ]);
    }

    /**
     * Test reset password with valid OTP.
     */
    public function test_customer_can_reset_password_with_valid_otp(): void
    {
        $customer = Customer::factory()->verified()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('oldpassword'),
        ]);

        $otp = CustomerOtp::factory()->passwordReset()->create([
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'otp' => '123456',
        ]);

        $data = [
            'email' => 'john@example.com',
            'otp' => '123456',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ];

        $response = $this->postJson("{$this->baseUrl}/reset-password", $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Verify password was changed
        $customer->refresh();
        $this->assertTrue(Hash::check('newpassword123', $customer->password));
    }

    /**
     * Test reset password fails with invalid OTP.
     */
    public function test_reset_password_fails_with_invalid_otp(): void
    {
        $customer = Customer::factory()->verified()->create([
            'email' => 'john@example.com',
        ]);

        CustomerOtp::factory()->passwordReset()->create([
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'otp' => '123456',
        ]);

        $data = [
            'email' => 'john@example.com',
            'otp' => '999999', // Wrong OTP
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ];

        $response = $this->postJson("{$this->baseUrl}/reset-password", $data);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'OTP_INVALID',
                ],
            ]);
    }

    /**
     * Test delete account requires authentication.
     */
    public function test_delete_account_requires_authentication(): void
    {
        $response = $this->deleteJson("{$this->baseUrl}/account");

        $response->assertStatus(401);
    }

    /**
     * Test customer can delete their account.
     */
    public function test_customer_can_delete_their_account(): void
    {
        $customer = Customer::factory()->verified()->create([
            'email' => 'john@example.com',
        ]);

        $token = JWTAuth::fromUser($customer);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("{$this->baseUrl}/account");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Account deleted successfully.',
            ]);

        // Verify customer is soft deleted
        $this->assertSoftDeleted('customers', [
            'id' => $customer->id,
        ]);
    }

    /**
     * Test JWT token is valid after login.
     */
    public function test_jwt_token_is_valid_after_login(): void
    {
        $customer = Customer::factory()->verified()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $loginResponse = $this->postJson("{$this->baseUrl}/login", [
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $token = $loginResponse->json('data.token');

        // Use token to access protected endpoint
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("{$this->baseUrl}/account");

        $response->assertStatus(200);
    }

    /**
     * Test JWT token is invalid after account deletion.
     */
    public function test_jwt_token_is_invalid_after_account_deletion(): void
    {
        $customer = Customer::factory()->verified()->create([
            'email' => 'john@example.com',
        ]);

        $token = JWTAuth::fromUser($customer);

        // Delete account
        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("{$this->baseUrl}/account");

        // Try to use the same token again
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("{$this->baseUrl}/account");

        $response->assertStatus(401);
    }

    /**
     * Test customer registration with avatar upload.
     */
    public function test_customer_can_register_with_avatar(): void
    {
        $avatar = \Illuminate\Http\UploadedFile::fake()->image('avatar.jpg', 100, 100);

        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'country_code' => '+1',
            'birthdate' => '1990-01-01',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'avatar' => $avatar,
        ];

        $response = $this->postJson("{$this->baseUrl}/register", $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $customer = Customer::where('email', 'john@example.com')->first();
        $this->assertNotNull($customer->avatar);
    }

    /**
     * Test customer logout requires authentication.
     */
    public function test_logout_requires_authentication(): void
    {
        $response = $this->postJson("{$this->baseUrl}/logout");

        $response->assertStatus(401);
    }

    /**
     * Test customer can logout successfully.
     */
    public function test_customer_can_logout(): void
    {
        $customer = Customer::factory()->verified()->create([
            'email' => 'john@example.com',
        ]);

        $token = JWTAuth::fromUser($customer);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson("{$this->baseUrl}/logout");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully.',
            ]);

        // Verify token is invalidated (try to use it again)
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson("{$this->baseUrl}/logout");

        $response->assertStatus(401);
    }

    /**
     * Test refresh token requires authentication.
     */
    public function test_refresh_token_requires_authentication(): void
    {
        $response = $this->postJson("{$this->baseUrl}/refresh-token");

        $response->assertStatus(401);
    }

    /**
     * Test customer can refresh token.
     */
    public function test_customer_can_refresh_token(): void
    {
        $customer = Customer::factory()->verified()->create([
            'email' => 'john@example.com',
        ]);

        $oldToken = JWTAuth::fromUser($customer);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$oldToken}",
        ])->postJson("{$this->baseUrl}/refresh-token");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'token',
                ],
                'message',
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Token refreshed successfully.',
            ]);

        $newToken = $response->json('data.token');
        $this->assertNotEmpty($newToken);
        $this->assertNotEquals($oldToken, $newToken);

        // Verify new token works
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$newToken}",
        ])->postJson("{$this->baseUrl}/logout");

        $response->assertStatus(200);
    }

    /**
     * Test refresh token generates different token.
     */
    public function test_refresh_token_generates_different_token(): void
    {
        $customer = Customer::factory()->verified()->create([
            'email' => 'john@example.com',
        ]);

        $oldToken = JWTAuth::fromUser($customer);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$oldToken}",
        ])->postJson("{$this->baseUrl}/refresh-token");

        $response->assertStatus(200);

        $newToken = $response->json('data.token');
        $this->assertNotEmpty($newToken);
        $this->assertNotEquals($oldToken, $newToken);

        // Verify new token is valid and can be used
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$newToken}",
        ])->getJson("{$this->baseUrl}/me");

        $response->assertStatus(200);
    }

    /**
     * Test get customer data requires authentication.
     */
    public function test_get_customer_data_requires_authentication(): void
    {
        $response = $this->getJson("{$this->baseUrl}/me");

        $response->assertStatus(401);
    }

    /**
     * Test customer can get their own data.
     */
    public function test_customer_can_get_their_own_data(): void
    {
        $customer = Customer::factory()->verified()->create([
            'email' => 'john@example.com',
            'name' => 'John Doe',
            'phone' => '1234567890',
            'country_code' => '+1',
            'birthdate' => '1990-01-01',
        ]);

        $token = JWTAuth::fromUser($customer);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson("{$this->baseUrl}/me");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'phone',
                    'country_code',
                    'birthdate',
                    'is_verified',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $customer->id,
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'phone' => '1234567890',
                    'country_code' => '+1',
                    'is_verified' => true,
                ],
            ]);
    }

    /**
     * Test get customer data returns correct format.
     */
    public function test_get_customer_data_returns_correct_format(): void
    {
        $customer = Customer::factory()->verified()->create([
            'email' => 'john@example.com',
        ]);

        $token = JWTAuth::fromUser($customer);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson("{$this->baseUrl}/me");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'phone',
                    'country_code',
                    'birthdate',
                    'avatar',
                    'foodics_customer_id',
                    'is_verified',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    }
}
