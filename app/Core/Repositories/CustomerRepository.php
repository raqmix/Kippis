<?php

namespace App\Core\Repositories;

use App\Core\Models\Customer;
use Illuminate\Support\Facades\Hash;

class CustomerRepository
{
    /**
     * Create a new customer.
     *
     * @param array $data
     * @return Customer
     */
    public function create(array $data): Customer
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        return Customer::create($data);
    }

    /**
     * Find customer by email.
     *
     * @param string $email
     * @param bool $withTrashed
     * @return Customer|null
     */
    public function findByEmail(string $email, bool $withTrashed = false): ?Customer
    {
        $query = Customer::where('email', $email);

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->first();
    }

    /**
     * Find customer by social provider ID.
     *
     * @param string $provider  'google' or 'apple'
     * @param string $providerId
     * @return Customer|null
     */
    public function findByProviderId(string $provider, string $providerId): ?Customer
    {
        $column = $provider . '_id';

        // Include soft-deleted rows so returning users can be restored.
        return Customer::withTrashed()->where($column, $providerId)->first();
    }

    /**
     * Find customer by phone number.
     *
     * @param string $phone
     * @return Customer|null
     */
    public function findByPhone(string $phone): ?Customer
    {
        return Customer::where('phone', $phone)->first();
    }

    /**
     * Find customer by ID.
     *
     * @param int $id
     * @return Customer|null
     */
    public function findById(int $id): ?Customer
    {
        return Customer::find($id);
    }

    /**
     * Update customer.
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $customer = $this->findById($id);

        if (!$customer) {
            return false;
        }

        // Hash password if provided
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        return $customer->update($data);
    }

    /**
     * Update customer password.
     *
     * @param int $id
     * @param string $password
     * @return bool
     */
    public function updatePassword(int $id, string $password): bool
    {
        return $this->update($id, ['password' => Hash::make($password)]);
    }
}
