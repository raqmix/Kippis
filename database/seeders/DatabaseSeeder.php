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
            RolePermissionSeeder::class,
            AdminSeeder::class,
            DefaultPagesSeeder::class,
            NotificationSeeder::class,
            StoreSeeder::class,
            ChannelSeeder::class,
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
