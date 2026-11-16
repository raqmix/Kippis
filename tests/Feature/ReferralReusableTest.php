<?php

namespace Tests\Feature;

use App\Core\Models\Customer;
use App\Core\Models\CustomerRedeemWallet;
use App\Core\Models\Order;
use App\Core\Models\Referral;
use App\Core\Models\Setting;
use App\Events\ReferralRewardIssued;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/** Brief section C: Bring a Friend (reusable code, free_coffee mode). */
class ReferralReusableTest extends TestCase
{
    use RefreshDatabase;

    private Customer $inviter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inviter = Customer::factory()->create();
    }

    private function code(?Customer $customer = null): array
    {
        return $this->actingAs($customer ?? $this->inviter, 'api')->getJson('/api/v1/referral/code')
            ->assertOk()->json('data');
    }

    private function apply(Customer $invitee, string $code)
    {
        return $this->actingAs($invitee, 'api')->postJson('/api/v1/referral/apply', ['code' => $code]);
    }

    private function complete(Customer $customer, string $platform = 'mobile'): Order
    {
        $order = Order::factory()->create([
            'customer_id' => $customer->id, 'status' => 'received', 'total' => 100, 'platform' => $platform,
        ]);
        $order->update(['status' => 'completed']);

        return $order;
    }

    private function points(Customer $c): int
    {
        return (int) ($c->loyaltyWallet()->first()?->points ?? 0);
    }

    public function test_code_is_personal_stable_and_reports_total_conversions(): void
    {
        $first = $this->code();
        $second = $this->code();

        $this->assertSame($first['referral_code'], $second['referral_code']);
        $this->assertSame($first['referral_code'], $this->inviter->fresh()->referral_code);
        $this->assertSame(0, $first['total_conversions']);
        $this->assertSame(0, $first['conversions_this_month']);
        $this->assertSame('points', $first['reward_type']);
        foreach (['share_url', 'share_text', 'monthly_cap', 'inviter_points_reward', 'invitee_points_reward'] as $key) {
            $this->assertArrayHasKey($key, $first);
        }
        $this->assertNotSame($first['referral_code'], $this->code(Customer::factory()->create())['referral_code']);
    }

    public function test_one_code_works_for_many_friends_in_points_mode(): void
    {
        $code = $this->code()['referral_code'];
        $a = Customer::factory()->create();
        $b = Customer::factory()->create();

        $this->apply($a, $code)->assertOk()->assertJsonPath('data.message', 'Referral applied. Points have been awarded.');
        $this->apply($b, strtolower($code))->assertOk();

        $this->assertSame(60, $this->points($this->inviter));
        $this->assertSame(30, $this->points($a));
        $this->assertSame(30, $this->points($b));
        $this->assertSame(2, Referral::where('inviter_id', $this->inviter->id)->where('status', 'converted')->count());
        $this->assertSame(2, $this->code()['total_conversions']);
        $this->assertSame(2, $this->code()['conversions_this_month']);
    }

    public function test_cannot_use_own_code_twice_or_invalid_code(): void
    {
        $code = $this->code()['referral_code'];
        $friend = Customer::factory()->create();

        $this->apply($this->inviter, $code)->assertStatus(422);
        $this->apply($friend, 'NOPE-123456')->assertStatus(422);
        $this->apply($friend, $code)->assertOk();
        $this->apply($friend, $code)->assertStatus(422); // already used a code
        $this->assertSame(30, $this->points($friend));
    }

    public function test_monthly_cap_limits_a_code(): void
    {
        Setting::set('referral.monthly_cap', 2, 'number', 'referral');
        $code = $this->code()['referral_code'];

        $this->apply(Customer::factory()->create(), $code)->assertOk();
        $this->apply(Customer::factory()->create(), $code)->assertOk();
        $blocked = Customer::factory()->create();
        $this->apply($blocked, $code)->assertStatus(422);
        $this->assertSame(0, $this->points($blocked));
    }

    public function test_legacy_pending_code_still_works_and_is_kept_as_the_personal_code(): void
    {
        Referral::create(['inviter_id' => $this->inviter->id, 'referral_code' => 'KIPPIS-LEGACY', 'status' => 'pending']);
        $a = Customer::factory()->create();
        $b = Customer::factory()->create();

        // Old app handed out the code before customers.referral_code existed.
        $this->assertSame('KIPPIS-LEGACY', $this->code()['referral_code']);

        $this->apply($a, 'KIPPIS-LEGACY')->assertOk();
        $this->apply($b, 'KIPPIS-LEGACY')->assertOk(); // no longer single-use

        $rows = Referral::where('inviter_id', $this->inviter->id)->get();
        $this->assertCount(2, $rows); // the open row became the first invitee's
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $rows->pluck('invitee_id')->all());
        $this->assertSame(60, $this->points($this->inviter));
    }

    public function test_legacy_code_resolves_when_inviter_already_has_a_different_personal_code(): void
    {
        Referral::create(['inviter_id' => $this->inviter->id, 'referral_code' => 'KIPPIS-OLD111', 'status' => 'pending']);
        $this->inviter->forceFill(['referral_code' => 'KIPPIS-NEW222'])->save();
        $friend = Customer::factory()->create();

        $this->apply($friend, 'KIPPIS-OLD111')->assertOk();

        $this->assertSame($this->inviter->id, Referral::where('invitee_id', $friend->id)->value('inviter_id'));
    }

    public function test_migration_backfills_customer_codes_from_referrals(): void
    {
        $other = Customer::factory()->create();
        $none = Customer::factory()->create();
        Referral::create(['inviter_id' => $this->inviter->id, 'referral_code' => 'KIPPIS-USED01', 'status' => 'converted', 'invitee_id' => $other->id]);
        Referral::create(['inviter_id' => $this->inviter->id, 'referral_code' => 'KIPPIS-OPEN02', 'status' => 'pending']);
        Referral::create(['inviter_id' => $other->id, 'referral_code' => 'KIPPIS-OTHER3', 'status' => 'registered', 'invitee_id' => $none->id]);
        DB::table('customers')->update(['referral_code' => null]);

        (require database_path('migrations/2026_11_16_000003_add_streak_multiplier_and_referral_code.php'))->up();

        $this->assertSame('KIPPIS-OPEN02', $this->inviter->fresh()->referral_code); // the open code wins
        $this->assertSame('KIPPIS-OTHER3', $other->fresh()->referral_code);
        $this->assertNull($none->fresh()->referral_code);
    }

    public function test_free_coffee_mode_rewards_both_on_first_completed_app_order_only_once(): void
    {
        Event::fake([ReferralRewardIssued::class]);
        Setting::set('referral.reward_type', 'free_coffee', 'string', 'referral');
        $code = $this->code()['referral_code'];
        $friend = Customer::factory()->create();

        $this->apply($friend, $code)->assertOk()->assertJsonPath('data.reward_type', 'free_coffee');
        $referral = Referral::where('invitee_id', $friend->id)->first();
        $this->assertSame('registered', $referral->status);
        $this->assertSame(0, $this->points($this->inviter));
        $this->assertSame(0, $this->points($friend));
        $this->assertSame(0, $this->code()['total_conversions']);

        // Web / kiosk orders don't trigger it.
        $this->complete($friend, 'web');
        $this->complete($friend, 'kiosk');
        $this->assertSame('registered', $referral->fresh()->status);
        $this->assertSame(0, CustomerRedeemWallet::where('source', 'referral')->count());

        $this->complete($friend, 'mobile');

        $referral->refresh();
        $this->assertSame('converted', $referral->status);
        $this->assertNotNull($referral->converted_at);
        foreach ([$this->inviter, $friend] as $customer) {
            $gifts = CustomerRedeemWallet::where('customer_id', $customer->id)->where('source', 'referral')->get();
            $this->assertCount(1, $gifts);
            $this->assertSame('free_item', $gifts[0]->reward_type);
            $this->assertSame('available', $gifts[0]->status);
        }
        Event::assertDispatchedTimes(ReferralRewardIssued::class, 1);
        Event::assertDispatched(ReferralRewardIssued::class, fn ($e) => $e->inviter->is($this->inviter) && $e->invitee->is($friend));

        // Second app order: nothing more.
        $this->complete($friend, 'mobile');
        $this->assertSame(2, CustomerRedeemWallet::where('source', 'referral')->count());
        Event::assertDispatchedTimes(ReferralRewardIssued::class, 1);
        $this->assertSame(1, $this->code()['total_conversions']);
    }

    public function test_free_coffee_mode_counts_pending_friends_against_the_monthly_cap(): void
    {
        Setting::set('referral.reward_type', 'free_coffee', 'string', 'referral');
        Setting::set('referral.monthly_cap', 1, 'number', 'referral');
        $code = $this->code()['referral_code'];

        $this->apply(Customer::factory()->create(), $code)->assertOk();
        $this->apply(Customer::factory()->create(), $code)->assertStatus(422);
    }

    public function test_points_mode_order_completion_pays_nothing_extra(): void
    {
        $code = $this->code()['referral_code'];
        $friend = Customer::factory()->create();
        $this->apply($friend, $code)->assertOk();

        $this->complete($friend, 'mobile');

        $this->assertSame(0, CustomerRedeemWallet::where('source', 'referral')->count());
    }
}
