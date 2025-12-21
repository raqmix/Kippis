<?php

namespace Database\Seeders;

use App\Core\Models\Customer;
use App\Core\Models\SupportTicket;
use Illuminate\Database\Seeder;

class SupportTicketSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = Customer::take(5)->get();
        $statuses = ['open', 'in_progress', 'closed'];
        $priorities = ['low', 'medium', 'high'];

        // Create tickets with customers
        foreach ($customers as $customer) {
            SupportTicket::create([
                'name' => $customer->name,
                'email' => $customer->email,
                'subject' => 'Order Issue - ' . fake()->sentence(3),
                'message' => fake()->paragraph(3),
                'status' => fake()->randomElement($statuses),
                'priority' => fake()->randomElement($priorities),
                'customer_id' => $customer->id,
            ]);

            SupportTicket::create([
                'name' => $customer->name,
                'email' => $customer->email,
                'subject' => 'Payment Problem - ' . fake()->sentence(3),
                'message' => fake()->paragraph(3),
                'status' => fake()->randomElement($statuses),
                'priority' => fake()->randomElement($priorities),
                'customer_id' => $customer->id,
            ]);
        }

        // Create tickets without customers (guest tickets)
        for ($i = 0; $i < 10; $i++) {
            SupportTicket::create([
                'name' => fake()->name(),
                'email' => fake()->unique()->safeEmail(),
                'subject' => 'General Inquiry - ' . fake()->sentence(3),
                'message' => fake()->paragraph(3),
                'status' => fake()->randomElement($statuses),
                'priority' => fake()->randomElement($priorities),
                'customer_id' => null,
            ]);
        }

        // Create some high priority open tickets
        for ($i = 0; $i < 3; $i++) {
            $customer = $customers->random();
            SupportTicket::create([
                'name' => $customer->name,
                'email' => $customer->email,
                'subject' => 'URGENT: ' . fake()->sentence(3),
                'message' => fake()->paragraph(3),
                'status' => 'open',
                'priority' => 'high',
                'customer_id' => $customer->id,
            ]);
        }
    }
}
