<?php

namespace Tests\Feature;

use App\Core\Models\Admin;
use App\Core\Models\PromoCode;
use App\Filament\Resources\PromoCodeResource\Pages\CreatePromoCode;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** An empty "Minimum order amount" must save as 0, not crash on the NOT NULL column. */
class PromoCodeMinimumOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_model_stores_null_minimum_as_zero(): void
    {
        $promo = PromoCode::factory()->create(['minimum_order_amount' => null]);
        $this->assertSame('0.00', $promo->fresh()->minimum_order_amount);
    }

    public function test_admin_can_create_promo_with_empty_minimum_order(): void
    {
        Permission::findOrCreate('manage_promo_codes', 'admin');
        $admin = Admin::factory()->create();
        $admin->givePermissionTo('manage_promo_codes');
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreatePromoCode::class)
            ->fillForm([
                'code' => 'test20',
                'discount_type' => PromoCode::TYPE_PERCENTAGE,
                'discount_value' => 20,
                'minimum_order_amount' => null,
                'valid_from' => now()->addDay(),
                'valid_to' => now()->addMonth(),
                'usage_limit' => 300,
                'usage_per_user_limit' => 1,
                'active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $promo = PromoCode::where('code', 'TEST20')->firstOrFail();
        $this->assertSame('0.00', $promo->minimum_order_amount);
    }
}
