<?php

namespace Tests\Feature;

use App\Core\Models\Customer;
use App\Core\Models\PartnerCode;
use App\Core\Models\Order;
use App\Core\Models\Referral;
use App\Filament\Resources\PartnerCodeResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Brief section D: partner codes and the birthdate lock. */
class PartnerCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'name' => 'Test User',
            'email' => 'new'.uniqid().'@example.com',
            'password' => 'Passw0rd!x',
            'password_confirmation' => 'Passw0rd!x',
        ], $extra);
    }

    private function register(array $extra = [])
    {
        return $this->postJson('/api/v1/auth/register', $this->payload($extra));
    }

    private function partner(string $code = 'ACME10', array $attrs = []): PartnerCode
    {
        return PartnerCode::create(array_merge(['code' => $code, 'name' => 'Acme Test'], $attrs));
    }

    public function test_code_is_stored_uppercase_and_lookup_is_case_insensitive(): void
    {
        $partner = $this->partner('acme10');

        $this->assertSame('ACME10', $partner->fresh()->code);
        $this->assertSame($partner->id, PartnerCode::findActive(' Acme10 ')->id);
    }

    public function test_register_with_partner_code_tags_customer(): void
    {
        $partner = $this->partner();

        $this->register(['partner_code' => 'acme10'])->assertCreated()
            ->assertJsonPath('data.partner_code.code', 'ACME10')
            ->assertJsonPath('data.partner_code.name', 'Acme Test');

        $customer = Customer::latest('id')->first();
        $this->assertSame($partner->id, $customer->partner_code_id);
        $this->assertNotNull($customer->partner_code_set_at);
    }

    public function test_register_with_invalid_or_inactive_partner_code_is_422(): void
    {
        $this->partner('OFF', ['is_active' => false]);

        $this->register(['partner_code' => 'NOPE'])->assertStatus(422);
        $this->register(['partner_code' => 'OFF'])->assertStatus(422);
        $this->assertSame(0, Customer::count());
    }

    public function test_signup_code_resolves_partner_first(): void
    {
        $partner = $this->partner('SHARED');
        $inviter = Customer::factory()->create();
        Referral::create(['inviter_id' => $inviter->id, 'referral_code' => 'SHARED', 'status' => 'pending']);

        $this->register(['signup_code' => 'shared'])->assertCreated();

        $customer = Customer::latest('id')->first();
        $this->assertSame($partner->id, $customer->partner_code_id);
        $this->assertNull(Referral::where('referral_code', 'SHARED')->first()->invitee_id);
    }

    public function test_signup_code_falls_back_to_referral_code(): void
    {
        $inviter = Customer::factory()->create();
        Referral::create(['inviter_id' => $inviter->id, 'referral_code' => 'FRIEND1', 'status' => 'pending']);

        $this->register(['signup_code' => 'FRIEND1'])->assertCreated()
            ->assertJsonPath('data.partner_code', null);

        $customer = Customer::latest('id')->first();
        $this->assertNull($customer->partner_code_id);
        $this->assertSame($customer->id, Referral::where('referral_code', 'FRIEND1')->first()->invitee_id);
    }

    public function test_invalid_signup_code_is_422_with_message(): void
    {
        $this->register(['signup_code' => 'WHATEVER'])
            ->assertStatus(422)
            ->assertJsonPath('error.errors.signup_code.0', 'That code is not valid.');
        $this->assertSame(0, Customer::count());
    }

    public function test_set_partner_code_later_once_only(): void
    {
        $this->partner();
        $this->partner('OTHER', ['name' => 'Other']);
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'api')->postJson('/api/v1/auth/me/partner-code', ['code' => 'nope'])
            ->assertStatus(422);

        $this->actingAs($customer, 'api')->postJson('/api/v1/auth/me/partner-code', ['code' => 'acme10'])
            ->assertOk()
            ->assertJsonPath('data.partner_code.code', 'ACME10');

        $this->actingAs($customer, 'api')->postJson('/api/v1/auth/me/partner-code', ['code' => 'OTHER'])
            ->assertStatus(422);
        $this->assertSame('ACME10', $customer->fresh()->partnerCode->code);
    }

    public function test_profile_shows_partner_code_and_lock_flag(): void
    {
        $partner = $this->partner();
        $customer = Customer::factory()->create(['birthdate' => null]);

        $this->actingAs($customer, 'api')->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.partner_code', null)
            ->assertJsonPath('data.birthdate_locked', false);

        $customer->applyPartnerCode($partner);
        $customer->update(['birthdate' => '1995-05-05']);

        $this->actingAs($customer, 'api')->getJson('/api/v1/auth/me')
            ->assertJsonPath('data.partner_code.name', 'Acme Test')
            ->assertJsonPath('data.birthdate_locked', true);
    }

    public function test_profile_update_cannot_change_partner_code(): void
    {
        $partner = $this->partner();
        $other = $this->partner('OTHER');
        $customer = Customer::factory()->create();
        $customer->applyPartnerCode($partner);

        $this->actingAs($customer, 'api')->putJson('/api/v1/auth/me', ['partner_code_id' => $other->id, 'name' => 'New'])
            ->assertOk();
        $this->assertSame($partner->id, $customer->fresh()->partner_code_id);
    }

    public function test_birthdate_can_be_set_when_null_then_is_locked(): void
    {
        $customer = Customer::factory()->create(['birthdate' => null]);

        $this->actingAs($customer, 'api')->putJson('/api/v1/auth/me', ['birthdate' => '1990-02-03'])
            ->assertOk()
            ->assertJsonPath('data.birthdate', '1990-02-03')
            ->assertJsonPath('data.birthdate_locked', true);

        $this->actingAs($customer, 'api')->putJson('/api/v1/auth/me', ['birthdate' => '1991-02-03'])
            ->assertStatus(422)
            ->assertJsonPath('error.errors.birthdate.0', 'Birthday can\'t be changed; contact support.');
        $this->assertSame('1990-02-03', $customer->fresh()->birthdate->toDateString());

        // Same value again and other fields still work.
        $this->actingAs($customer, 'api')->putJson('/api/v1/auth/me', ['birthdate' => '1990-02-03', 'name' => 'Renamed'])
            ->assertOk();
    }

    public function test_admin_edit_can_change_birthdate(): void
    {
        $customer = Customer::factory()->create(['birthdate' => '1990-01-01']);

        // Admin path is the model/Filament form, not the customer endpoint.
        $customer->update(['birthdate' => '1985-12-12']);

        $this->assertSame('1985-12-12', $customer->fresh()->birthdate->toDateString());
    }

    public function test_filament_counts_signups_and_completed_orders(): void
    {
        $partner = $this->partner();
        $other = $this->partner('OTHER');
        $tagged = Customer::factory()->count(2)->create();
        $tagged->each->applyPartnerCode($partner);
        Customer::factory()->create()->applyPartnerCode($other);

        Order::factory()->create(['customer_id' => $tagged[0]->id, 'status' => 'completed', 'platform' => 'mobile', 'created_at' => '2026-11-10']);
        Order::factory()->create(['customer_id' => $tagged[1]->id, 'status' => 'completed', 'platform' => 'web', 'created_at' => '2026-10-01']);
        Order::factory()->create(['customer_id' => $tagged[1]->id, 'status' => 'cancelled']);
        Order::factory()->create(['customer_id' => Customer::factory()->create()->id, 'status' => 'completed']);

        $this->assertSame(2, PartnerCodeResource::signupCount($partner));
        $this->assertSame(2, PartnerCodeResource::orderCount($partner));
        $this->assertSame(1, PartnerCodeResource::orderCount($partner, '2026-11-01', '2026-11-30'));
        $this->assertSame(1, PartnerCodeResource::signupCount($other));
        $this->assertSame(0, PartnerCodeResource::orderCount($other));
    }

    public function test_filament_list_page_renders_counts(): void
    {
        \Spatie\Permission\Models\Permission::findOrCreate('manage_promo_codes', 'admin');
        $admin = \App\Core\Models\Admin::factory()->create();
        $admin->givePermissionTo('manage_promo_codes');
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
        $this->actingAs($admin, 'admin');

        $partner = $this->partner();
        $c = Customer::factory()->create();
        $c->applyPartnerCode($partner);
        Order::factory()->create(['customer_id' => $c->id, 'status' => 'completed']);

        \Livewire\Livewire::test(PartnerCodeResource\Pages\ManagePartnerCodes::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$partner])
            ->assertTableColumnStateSet('signups', 1, $partner)
            ->assertTableColumnStateSet('orders', 1, $partner);
    }
}
