<?php

namespace Tests\Feature;

use App\Core\Models\Customer;
use App\Core\Repositories\CustomerRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_password_stores_a_single_hash_so_login_can_match(): void
    {
        $customer = Customer::factory()->create(['is_verified' => true]);
        $repo = app(CustomerRepository::class);

        $newPassword = 'joujouITI2001@#$';

        $repo->updatePassword($customer->id, $newPassword);

        $customer->refresh();

        // The smoking gun for the historic double-hash bug:
        // Hash::check(plain, doubleHashed) is always false → every reset locked
        // the customer out. Login uses Hash::check directly, so this assertion
        // mirrors the production check.
        $this->assertTrue(
            Hash::check($newPassword, $customer->password),
            'Hash::check failed against the stored password — updatePassword likely double-hashed.'
        );
    }
}
