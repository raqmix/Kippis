<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RolePermissionSeeder::class,
            AdminSeeder::class,
            DefaultPagesSeeder::class,
            NotificationSeeder::class,
            StoreSeeder::class,
            ChannelSeeder::class,
            PaymentMethodSeeder::class,
            CustomerSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            ModifierSeeder::class,
            ExtraSeeder::class,
            PromoCodeSeeder::class,
            LoyaltyWalletSeeder::class,
            CartSeeder::class,
            OrderSeeder::class,
        ]);
    }
}
