<?php

namespace App\Core\Services;

use App\Core\Models\Customer;
use Illuminate\Support\Facades\Log;

/**
 * Foodics Customer Service (Stub)
 *
 * This service prepares for future integration with Foodics POS system.
 * Currently, it's a stub that logs the intent to create a customer in Foodics.
 *
 * When ready to integrate:
 * 1. Add Foodics API credentials to config
 * 2. Implement actual API calls to Foodics
 * 3. Update customer's foodics_customer_id with the response
 */
class FoodicsCustomerService
{
    /**
     * Create customer in Foodics system.
     *
     * @param Customer $customer
     * @return string|null Returns Foodics customer ID or null if not implemented
     */
    public function createCustomer(Customer $customer): ?string
    {
        // Stub implementation - log intent
        Log::info('Foodics customer creation intent', [
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'name' => $customer->name,
        ]);

        // TODO: Implement actual Foodics API integration
        // Example:
        // $response = Http::withHeaders([
        //     'Authorization' => 'Bearer ' . config('foodics.api_token'),
        // ])->post(config('foodics.api_url') . '/customers', [
        //     'name' => $customer->name,
        //     'email' => $customer->email,
        //     'phone' => $customer->phone,
        // ]);
        //
        // if ($response->successful()) {
        //     return $response->json()['data']['id'];
        // }

        return null;
    }
}
