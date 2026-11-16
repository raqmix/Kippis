<?php

namespace Tests\Feature;

use App\Core\Models\Admin;
use App\Core\Models\Category;
use App\Core\Models\Customer;
use App\Core\Models\LoyaltyTransaction;
use App\Core\Models\LoyaltyWallet;
use App\Core\Models\Product;
use App\Core\Models\Promotion;
use App\Core\Models\Store;
use App\Filament\Resources\StoreResource;
use App\Filament\Widgets\DraftsInboxWidget;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Regressions for errors seen in the prod log, 14–16 Nov. */
class ProdErrorFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_promotions_accept_arabic_indic_date(): void
    {
        $p = Promotion::create(['title' => 'P', 'offer_text' => '10% off', 'is_active' => true,
            'starts_at' => '2026-11-10 00:00:00', 'ends_at' => '2026-11-20 23:59:59']);

        $this->getJson('/api/v1/promotions?date=' . urlencode('٢٠٢٦-١١-١٥'))
            ->assertOk()->assertJsonPath('data.0.id', $p->id);
        $this->getJson('/api/v1/promotions?date=' . urlencode('۲۰۲۶-۱۱-۲۵'))
            ->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/promotions?date=2026-11-15')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_promotions_bad_date_is_422_not_500(): void
    {
        $this->getJson('/api/v1/promotions?date=garbage')->assertStatus(422);
    }

    public function test_register_accepts_arabic_indic_birthdate(): void
    {
        $res = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test', 'email' => 'digits@example.test', 'phone' => '+201000000123',
            'country_code' => '+20', 'password' => 'Password123!', 'password_confirmation' => 'Password123!',
            'birthdate' => '١٩٩٠-٠١-٠٢',
        ]);
        $this->assertArrayNotHasKey('birthdate', (array) $res->json('error.errors'), $res->getContent());
        $this->assertSame('1990-01-02', \App\Core\Models\Customer::where('email', 'digits@example.test')->first()?->birthdate?->toDateString());
    }

    public function test_prune_hard_deletes_customer_with_loyalty_history(): void
    {
        $gone = Customer::factory()->create();
        $wallet = LoyaltyWallet::factory()->create(['customer_id' => $gone->id]);
        LoyaltyTransaction::factory()->create(['wallet_id' => $wallet->id]);
        $gone->delete();
        DB::table('customers')->where('id', $gone->id)->update(['deleted_at' => now()->subYears(3)]);

        $kept = Customer::factory()->create();
        $keptWallet = LoyaltyWallet::factory()->create(['customer_id' => $kept->id]);
        LoyaltyTransaction::factory()->create(['wallet_id' => $keptWallet->id]);

        $this->artisan('data:prune')->assertSuccessful();

        $this->assertDatabaseMissing('customers', ['id' => $gone->id]);
        $this->assertDatabaseMissing('loyalty_wallets', ['id' => $wallet->id]);
        $this->assertDatabaseMissing('loyalty_transactions', ['wallet_id' => $wallet->id]);
        $this->assertDatabaseHas('loyalty_transactions', ['wallet_id' => $keptWallet->id]);
    }

    private function actAsSuperAdmin(): void
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->givePermissionTo(Permission::findOrCreate('manage_stores', 'admin'));
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_drafts_widget_renders_branch_names(): void
    {
        $this->actAsSuperAdmin();
        $store = Store::factory()->create(['name' => 'Zamalek', 'name_localized' => ['en' => 'Zamalek', 'ar' => 'الزمالك']]);
        $product = Product::factory()->create([
            'category_id' => Category::factory()->create()->id, 'is_draft' => true,
        ]);
        $product->stores()->attach($store->id);

        Livewire::test(DraftsInboxWidget::class)->assertOk()->assertSee('Zamalek');
    }

    public function test_branch_menu_title_uses_store_name(): void
    {
        $this->actAsSuperAdmin();
        $store = Store::factory()->create(['name' => 'Zamalek', 'name_localized' => ['en' => 'Zamalek', 'ar' => 'الزمالك']]);

        $this->get(StoreResource::getUrl('branch-menu', ['record' => $store]))
            ->assertOk()->assertSee('Zamalek');
    }
}
