<?php

namespace Tests\Feature;

use App\Core\Models\Customer;
use App\Core\Models\CustomerPushLog;
use App\Core\Models\CustomerRedeemWallet;
use App\Core\Models\LoyaltyTransaction;
use App\Core\Models\LoyaltyWallet;
use App\Core\Models\RedeemItem;
use App\Core\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\RecordingFcm;
use Tests\TestCase;

/** Brief section E: pushes:lifecycle-triggers. */
class PushLifecycleTriggersTest extends TestCase
{
    use RefreshDatabase;

    protected RecordingFcm $fcm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fcm = new RecordingFcm();
        $this->app->instance(FcmService::class, $this->fcm);
        Carbon::setTestNow(Carbon::parse('2026-11-16 11:00', 'Africa/Cairo')->utc());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function customer(array $a = []): Customer
    {
        return Customer::factory()->create($a + ['fcm_token' => 'tok-' . uniqid(), 'push_marketing_opt_in' => true]);
    }

    private function wallet(Customer $c, int $points): LoyaltyWallet
    {
        return LoyaltyWallet::updateOrCreate(['customer_id' => $c->id], ['points' => $points]);
    }

    private function item(int $cost, string $en = 'Free Latte', string $ar = 'لاتيه مجاني', bool $active = true): RedeemItem
    {
        return RedeemItem::create(['title_json' => ['en' => $en, 'ar' => $ar], 'points_cost' => $cost, 'is_active' => $active]);
    }

    private function run_(?string $only = null): void
    {
        $this->artisan('pushes:lifecycle-triggers', $only ? ['--only' => $only] : [])->assertExitCode(0);
    }

    private function gift(Customer $c, string $expiresCairo, string $status = 'available'): CustomerRedeemWallet
    {
        return CustomerRedeemWallet::create([
            'customer_id' => $c->id, 'source' => 'admin', 'reward_type' => 'free_item', 'status' => $status,
            'expires_at' => Carbon::parse($expiresCairo, 'Africa/Cairo')->utc(),
        ]);
    }

    // ---- gift_expiring -------------------------------------------------

    public function test_gift_expiring_three_days_ahead_only_once(): void
    {
        \App\Core\Models\Setting::set('pushes.marketing_requires_opt_in', true, 'boolean', 'pushes');
        $due = $this->customer();
        $soon = $this->customer();
        $later = $this->customer();
        $used = $this->customer();
        $out = $this->customer(['push_marketing_opt_in' => false]);
        $this->gift($due, '2026-11-19 20:00');
        $this->gift($soon, '2026-11-18 20:00');
        $this->gift($later, '2026-11-20 00:30');
        $this->gift($used, '2026-11-19 10:00', 'applied');
        $this->gift($out, '2026-11-19 10:00');

        $this->run_('gift_expiring');
        $this->run_('gift_expiring');

        $this->assertSame(['gift_expiring'], $this->fcm->types());
        $this->assertSame($due->fcm_token, $this->fcm->sent[0]['token']);
        $this->assertSame('my_rewards', $this->fcm->sent[0]['data']['screen']);
        $this->assertSame(1, CustomerPushLog::count(), 'no duplicate-skip rows on re-run');
    }

    public function test_gift_expiring_cairo_day_boundaries(): void
    {
        $edgeIn = $this->customer();
        $edgeOut = $this->customer();
        $this->gift($edgeIn, '2026-11-19 00:00:30');   // first minute of day+3 (Cairo)
        $this->gift($edgeOut, '2026-11-18 23:59:00');  // last minute of day+2
        $this->run_('gift_expiring');
        $this->assertSame([$edgeIn->fcm_token], array_column($this->fcm->sent, 'token'));
    }

    public function test_gift_expiring_one_push_for_customer_with_two_gifts(): void
    {
        $c = $this->customer();
        $this->gift($c, '2026-11-19 12:00');
        $this->gift($c, '2026-11-19 15:00');
        $this->run_('gift_expiring');
        $this->assertCount(1, $this->fcm->sent);
        $this->assertSame(1, CustomerPushLog::count());
    }

    // ---- points_expiring -----------------------------------------------

    private function earn(LoyaltyWallet $w, int $points, string $expiresCairo): void
    {
        LoyaltyTransaction::create([
            'wallet_id' => $w->id, 'type' => 'earned', 'points' => $points,
            'expires_at' => Carbon::parse($expiresCairo, 'Africa/Cairo')->utc(),
        ]);
    }

    public function test_points_expiring_within_two_weeks_once_per_month(): void
    {
        $due = $this->customer();
        $w = $this->wallet($due, 50);
        $this->earn($w, 50, '2026-11-25 10:00');
        $far = $this->customer();
        $this->earn($this->wallet($far, 50), 50, '2027-03-01 10:00');
        $spent = $this->customer(); // the expiring points were already redeemed
        $ws = $this->wallet($spent, 0);
        $this->earn($ws, 50, '2026-11-25 10:00');
        LoyaltyTransaction::create(['wallet_id' => $ws->id, 'type' => 'redeemed', 'points' => -50]);

        $this->run_('points_expiring');
        $this->assertSame(['points_expiring'], $this->fcm->types());
        $this->assertSame($due->fcm_token, $this->fcm->sent[0]['token']);
        $this->assertSame('loyalty', $this->fcm->sent[0]['data']['screen']);

        Carbon::setTestNow(Carbon::parse('2026-11-17 11:00', 'Africa/Cairo')->utc());
        $this->run_('points_expiring');
        $this->assertCount(1, $this->fcm->sent, 'same month: no repeat');

        Carbon::setTestNow(Carbon::parse('2026-12-01 11:00', 'Africa/Cairo')->utc());
        $this->earn($w, 10, '2026-12-10 10:00');
        $this->run_('points_expiring');
        $this->assertCount(2, $this->fcm->sent, 'new month: may notify again');
    }

    // ---- enough_points_for_gift ----------------------------------------

    public function test_enough_points_once_per_crossing(): void
    {
        $this->item(100);
        $this->item(300, 'Big Gift');
        $c = $this->customer();
        $w = $this->wallet($c, 120);
        $low = $this->customer();
        $this->wallet($low, 99);

        $this->run_('enough_points_for_gift');
        $this->assertSame(['enough_points_for_gift'], $this->fcm->types());
        $this->assertStringContainsString('You have enough points for a Free Latte.', $this->fcm->sent[0]['body']);
        $this->assertStringContainsString('لاتيه مجاني', $this->fcm->sent[0]['body']);
        $this->assertSame('loyalty', $this->fcm->sent[0]['data']['screen']);

        // next days: still above, no repeat (and no duplicate-skip spam)
        Carbon::setTestNow(Carbon::parse('2026-11-17 11:00', 'Africa/Cairo')->utc());
        $this->run_('enough_points_for_gift');
        $this->assertCount(1, $this->fcm->sent);
        $this->assertSame(1, CustomerPushLog::count());

        // spends points (new crossing epoch), earns back above the threshold: notified again
        $w->transactions()->create(['type' => 'redeemed', 'points' => -100]);
        $w->update(['points' => 20]);
        $w->transactions()->create(['type' => 'earned', 'points' => 100]);
        $w->update(['points' => 120]);
        Carbon::setTestNow(Carbon::parse('2026-11-18 11:00', 'Africa/Cairo')->utc());
        $this->run_('enough_points_for_gift');
        $this->assertCount(2, $this->fcm->sent);
    }

    public function test_enough_points_ignores_inactive_items_and_disabled_redemption(): void
    {
        $this->item(50, 'Hidden', 'x', false);
        $this->wallet($this->customer(), 500);
        $this->run_('enough_points_for_gift');
        $this->assertCount(0, $this->fcm->sent);

        $this->item(100);
        \App\Core\Models\Setting::set('loyalty.redemption_enabled', false, 'boolean', 'loyalty');
        $this->run_('enough_points_for_gift');
        $this->assertCount(0, $this->fcm->sent);
    }

    // ---- close_to_gift -------------------------------------------------

    public function test_close_to_gift_within_ten_points_once_a_week(): void
    {
        $this->item(100);
        $this->item(300, 'Big Gift', 'هدية كبيرة');
        $near = $this->customer();
        $this->wallet($near, 93);       // 7 away from 100
        $far = $this->customer();
        $this->wallet($far, 85);        // 15 away
        $nextTier = $this->customer();
        $this->wallet($nextTier, 295);  // already above 100, 5 away from 300
        $edge = $this->customer();
        $this->wallet($edge, 90);       // exactly 10 away

        $this->run_('close_to_gift');
        $this->assertCount(3, $this->fcm->sent);
        $byToken = [];
        foreach ($this->fcm->sent as $s) {
            $byToken[$s['token']] = $s;
        }
        $this->assertStringContainsString("You're 7 points away from a Free Latte!", $byToken[$near->fcm_token]['body']);
        $this->assertStringContainsString("You're 5 points away from a Big Gift!", $byToken[$nextTier->fcm_token]['body']);
        $this->assertStringContainsString("You're 10 points away", $byToken[$edge->fcm_token]['body']);
        $this->assertArrayNotHasKey($far->fcm_token, $byToken);
        $this->assertSame('loyalty', $byToken[$near->fcm_token]['data']['screen']);

        // same ISO week: no repeat; next week: again
        Carbon::setTestNow(Carbon::parse('2026-11-18 11:00', 'Africa/Cairo')->utc());
        $this->run_('close_to_gift');
        $this->assertCount(3, $this->fcm->sent);
        Carbon::setTestNow(Carbon::parse('2026-11-23 11:00', 'Africa/Cairo')->utc());
        $this->run_('close_to_gift');
        $this->assertCount(6, $this->fcm->sent);
    }

    // ---- command as a whole --------------------------------------------

    public function test_one_marketing_push_per_day_when_several_triggers_apply(): void
    {
        $this->item(100);
        $c = $this->customer();
        $this->wallet($c, 95);
        $this->gift($c, '2026-11-19 12:00');

        $this->run_();

        $this->assertSame(['gift_expiring'], $this->fcm->types(), 'gift_expiring has priority');
        $skipped = CustomerPushLog::where('status', 'skipped')->where('reason', 'daily_cap')->count();
        $this->assertSame(1, $skipped); // close_to_gift
    }

    public function test_command_is_scheduled_daily_at_eleven_cairo(): void
    {
        $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command, 'pushes:lifecycle-triggers'));
        $this->assertNotNull($event);
        $this->assertSame('0 11 * * *', $event->expression);
        $this->assertSame('Africa/Cairo', (string) $event->timezone);
    }
}
