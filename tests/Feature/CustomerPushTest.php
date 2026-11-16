<?php

namespace Tests\Feature;

use App\Core\Models\Customer;
use App\Core\Models\CustomerPushLog;
use App\Core\Models\CustomerRedeemWallet;
use App\Core\Models\LoyaltyTransaction;
use App\Core\Models\LoyaltyWallet;
use App\Core\Models\RedeemItem;
use App\Core\Models\ScheduledPush;
use App\Core\Models\Setting;
use App\Core\Services\FcmService;
use App\Events\BirthdayTreatIssued;
use App\Events\MonthlyDrawEntered;
use App\Events\OrderStatusUpdated;
use App\Events\OrderStreakAlmostComplete;
use App\Events\OrderStreakRewardIssued;
use App\Events\ReferralRewardIssued;
use App\Jobs\SendScheduledPushBatchJob;
use App\Listeners\NotifyCustomerOnOrderStatusUpdate;
use App\Listeners\SendCustomerPushes;
use App\Services\Notifications\CustomerPushService;
use App\Services\ScheduledPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\Support\RecordingFcm;
use Tests\TestCase;

/** Brief section E: customer pushes, daily cap, listeners, lifecycle triggers. */
class CustomerPushTest extends TestCase
{
    use RefreshDatabase;

    protected RecordingFcm $fcm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fcm = new RecordingFcm();
        $this->app->instance(FcmService::class, $this->fcm);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function at(string $cairo): void
    {
        Carbon::setTestNow(Carbon::parse($cairo, 'Africa/Cairo')->utc());
    }

    protected function customer(array $attrs = []): Customer
    {
        return Customer::factory()->create($attrs + ['fcm_token' => 'tok-' . uniqid(), 'push_marketing_opt_in' => true]);
    }

    protected function pushes(): CustomerPushService
    {
        return app(CustomerPushService::class);
    }

    protected function log(Customer $c, ?string $status = null)
    {
        return CustomerPushLog::where('customer_id', $c->id)->when($status, fn ($q) => $q->where('status', $status))->get();
    }

    // ---- service rules -------------------------------------------------

    public function test_marketing_cap_is_one_per_cairo_day_across_types(): void
    {
        $c = $this->customer();
        $this->at('2026-11-16 09:00');
        $this->assertSame('sent', $this->pushes()->send($c, 'gift_expiring')->status);
        $second = $this->pushes()->send($c, 'big_win_entry');
        $this->assertSame('skipped', $second->status);
        $this->assertSame('daily_cap', $second->reason);
        $this->assertCount(1, $this->fcm->sent);
        $this->assertCount(2, $this->log($c), 'skips are logged too');
    }

    public function test_cap_resets_at_cairo_midnight_not_utc_midnight(): void
    {
        $c = $this->customer();
        $this->at('2026-11-16 23:30'); // 21:30 UTC
        $this->assertSame('sent', $this->pushes()->send($c, 'gift_expiring')->status);

        $this->at('2026-11-16 23:59'); // same Cairo day, still 21:59 UTC
        $this->assertSame('daily_cap', $this->pushes()->send($c, 'big_win_entry')->reason);

        $this->at('2026-11-17 00:01'); // next Cairo day, but 22:01 UTC on the 16th
        $this->assertSame('sent', $this->pushes()->send($c, 'big_win_entry')->status);

        $this->at('2026-11-17 01:30'); // same new Cairo day, UTC date is the 16th still
        $this->assertSame('daily_cap', $this->pushes()->send($c, 'close_to_gift', ['n' => 3, 'gift' => 'x'])->reason);
    }

    public function test_transactional_push_still_sends_after_cap_and_does_not_use_it(): void
    {
        $c = $this->customer();
        $this->at('2026-11-16 12:00');
        $this->pushes()->send($c, 'gift_expiring');
        $this->assertSame('sent', $this->pushes()->send($c, 'streak_reward')->status);
        $this->assertSame('sent', $this->pushes()->send($c, 'referral_reward')->status);
        $this->assertSame(['gift_expiring', 'streak_reward', 'referral_reward'], $this->fcm->types());

        // and a transactional push earlier in the day does not eat the marketing slot
        $d = $this->customer();
        $this->pushes()->send($d, 'streak_reward');
        $this->assertSame('sent', $this->pushes()->send($d, 'gift_expiring')->status);
    }

    public function test_dedup_key_sends_once_per_customer_and_is_per_customer(): void
    {
        $a = $this->customer();
        $b = $this->customer();
        $this->at('2026-11-16 12:00');
        $this->assertSame('sent', $this->pushes()->send($a, 'streak_reward', [], [], 'k1')->status);
        $dup = $this->pushes()->send($a, 'streak_reward', [], [], 'k1');
        $this->assertSame('duplicate', $dup->reason);
        $this->assertSame('sent', $this->pushes()->send($b, 'streak_reward', [], [], 'k1')->status);
        $this->assertSame('sent', $this->pushes()->send($a, 'streak_reward', [], [], 'k2')->status);
        $this->assertCount(3, $this->fcm->sent);
    }

    public function test_cap_skipped_marketing_does_not_consume_its_dedup_key(): void
    {
        $c = $this->customer();
        $this->at('2026-11-16 12:00');
        $this->pushes()->send($c, 'gift_expiring');
        $this->assertSame('daily_cap', $this->pushes()->send($c, 'big_win_entry', [], [], 'bw')->reason);
        $this->at('2026-11-17 12:00');
        $this->assertSame('sent', $this->pushes()->send($c, 'big_win_entry', [], [], 'bw')->status);
    }

    public function test_marketing_reaches_customers_without_opt_in_by_default(): void
    {
        $c = $this->customer(['push_marketing_opt_in' => false]);
        $this->assertSame('sent', $this->pushes()->send($c, 'gift_expiring')->status);
    }

    public function test_opt_out_blocks_marketing_but_not_transactional(): void
    {
        \App\Core\Models\Setting::set('pushes.marketing_requires_opt_in', true, 'boolean', 'pushes');
        $c = $this->customer(['push_marketing_opt_in' => false]);
        $row = $this->pushes()->send($c, 'gift_expiring');
        $this->assertSame('not_opted_in', $row->reason);
        $this->assertSame('sent', $this->pushes()->send($c, 'streak_reward')->status);
        $this->assertSame(['streak_reward'], $this->fcm->types());
    }

    public function test_no_token_is_skipped_and_logged(): void
    {
        $c = $this->customer(['fcm_token' => null]);
        $this->assertSame('no_token', $this->pushes()->send($c, 'streak_reward')->reason);
        $this->assertSame('no_token', $this->pushes()->send($c, 'gift_expiring')->reason);
        $this->assertCount(0, $this->fcm->sent);
        $this->assertCount(2, $this->log($c, 'skipped'));
    }

    public function test_fcm_failure_is_logged_and_frees_key_and_cap(): void
    {
        $c = $this->customer();
        $this->fcm->fail = true;
        $this->assertSame('failed', $this->pushes()->send($c, 'gift_expiring', [], [], 'g1')->status);
        $this->fcm->fail = false;
        $this->assertSame('sent', $this->pushes()->send($c, 'gift_expiring', [], [], 'g1')->status);
    }

    public function test_fcm_http_rejection_is_a_failure_that_frees_key_and_cap(): void
    {
        $c = $this->customer();
        $this->fcm->reject = true;
        $row = $this->pushes()->send($c, 'gift_expiring', [], [], 'g1');
        $this->assertSame('failed', $row->status);
        $this->assertNull($row->dedup_key);
        $this->assertFalse($this->pushes()->marketingSentToday($c));

        $this->fcm->reject = false;
        $this->assertSame('sent', $this->pushes()->send($c, 'gift_expiring', [], [], 'g1')->status);
    }

    public function test_fcm_service_returns_false_on_http_error_and_true_on_success(): void
    {
        $fcm = new \App\Core\Services\FcmService();
        $token = new \ReflectionProperty($fcm, 'accessToken');
        $token->setAccessible(true);
        $token->setValue($fcm, 'test-access-token');

        \Illuminate\Support\Facades\Http::fake(['fcm.googleapis.com/*' => \Illuminate\Support\Facades\Http::sequence()
            ->push(['error' => ['status' => 'UNAVAILABLE']], 503)
            ->push(['name' => 'projects/x/messages/1'], 200)]);

        $this->assertFalse($fcm->sendDataToToken('t', 'T', 'B', []));
        $this->assertTrue($fcm->sendDataToToken('t', 'T', 'B', []));
    }

    public function test_payload_has_type_screen_and_bilingual_text(): void
    {
        $c = $this->customer();
        $this->pushes()->send($c, 'streak_almost', ['n' => 6], ['count' => 6]);
        $sent = $this->fcm->sent[0];
        $this->assertSame('streak_almost', $sent['data']['type']);
        $this->assertSame('streak', $sent['data']['screen']);
        $this->assertStringContainsString('6 days in a row!', $sent['body']);
        $this->assertStringContainsString('اليوم 6', $sent['body']);
        $this->assertStringContainsString(' • ', $sent['title']);
    }

    public function test_type_to_screen_table(): void
    {
        $expected = [
            'gift_expiring' => 'my_rewards', 'birthday' => 'my_rewards', 'referral_reward' => 'my_rewards',
            'streak_reward' => 'my_rewards', 'points_expiring' => 'loyalty', 'enough_points_for_gift' => 'loyalty',
            'close_to_gift' => 'loyalty', 'big_win_entry' => 'loyalty', 'streak_almost' => 'streak',
        ];
        foreach ($expected as $type => $screen) {
            $this->assertSame($screen, CustomerPushService::TYPES[$type][1], $type);
        }
        foreach (['referral_reward', 'streak_reward'] as $t) {
            $this->assertSame('transactional', CustomerPushService::TYPES[$t][0]);
        }
    }

    // ---- listeners -----------------------------------------------------

    public function test_listeners_are_registered_for_all_five_events(): void
    {
        foreach ([OrderStreakAlmostComplete::class, OrderStreakRewardIssued::class, MonthlyDrawEntered::class,
            ReferralRewardIssued::class, BirthdayTreatIssued::class] as $event) {
            $this->assertTrue(Event::hasListeners($event), $event);
        }
    }

    public function test_dispatching_an_event_sends_the_push(): void
    {
        $c = $this->customer();
        OrderStreakAlmostComplete::dispatch($c, 2, 3);
        $this->assertSame(['streak_almost'], $this->fcm->types());
    }

    public function test_streak_reward_listener_is_transactional_with_reward_dedup(): void
    {
        $c = $this->customer(['push_marketing_opt_in' => false]);
        $reward = CustomerRedeemWallet::create(['customer_id' => $c->id, 'source' => 'streak', 'reward_type' => 'free_item']);
        $listener = app(SendCustomerPushes::class);
        $listener->handleStreakReward(new OrderStreakRewardIssued($c, $reward));
        $listener->handleStreakReward(new OrderStreakRewardIssued($c, $reward));
        $this->assertSame(['streak_reward'], $this->fcm->types());
        $this->assertSame('my_rewards', $this->fcm->sent[0]['data']['screen']);
        $this->assertStringContainsString('Your free coffee is in My Rewards!', $this->fcm->sent[0]['body']);
    }

    public function test_big_win_entry_once_per_cairo_month(): void
    {
        $c = $this->customer();
        $order = \App\Core\Models\Order::factory()->create(['customer_id' => $c->id]);
        $listener = app(SendCustomerPushes::class);

        $this->at('2026-11-02 10:00');
        $listener->handleBigWinEntry(new MonthlyDrawEntered($c, $order));
        $this->at('2026-11-03 10:00');
        $listener->handleBigWinEntry(new MonthlyDrawEntered($c, $order));
        $this->assertSame(['big_win_entry'], $this->fcm->types());
        $this->assertNotNull(CustomerPushLog::where('dedup_key', 'big_win_entry:2026-11')->first());
        $this->assertSame('loyalty', $this->fcm->sent[0]['data']['screen']);

        $this->at('2026-12-01 10:00');
        $listener->handleBigWinEntry(new MonthlyDrawEntered($c, $order));
        $this->assertSame(['big_win_entry', 'big_win_entry'], $this->fcm->types());
    }

    public function test_big_win_month_boundary_uses_cairo_time(): void
    {
        $c = $this->customer();
        $order = \App\Core\Models\Order::factory()->create(['customer_id' => $c->id]);
        $this->at('2026-12-01 00:30'); // still 30 Nov in UTC
        app(SendCustomerPushes::class)->handleBigWinEntry(new MonthlyDrawEntered($c, $order));
        $this->assertNotNull(CustomerPushLog::where('dedup_key', 'big_win_entry:2026-12')->first());
    }

    public function test_referral_reward_notifies_both_customers(): void
    {
        $inviter = $this->customer();
        $invitee = $this->customer();
        app(SendCustomerPushes::class)->handleReferralReward(new ReferralRewardIssued($inviter, $invitee));
        $this->assertSame(['referral_reward', 'referral_reward'], $this->fcm->types());
        $tokens = array_column($this->fcm->sent, 'token');
        $this->assertEqualsCanonicalizing([$inviter->fcm_token, $invitee->fcm_token], $tokens);
        $this->assertSame('my_rewards', $this->fcm->sent[0]['data']['screen']);
    }

    public function test_birthday_listener_sends_marketing_push_once(): void
    {
        $c = $this->customer();
        $reward = CustomerRedeemWallet::create([
            'customer_id' => $c->id, 'source' => 'birthday', 'source_ref' => 'birthday:2026', 'reward_type' => 'free_item',
        ]);
        $listener = app(SendCustomerPushes::class);
        $listener->handleBirthday(new BirthdayTreatIssued($c, $reward));
        $listener->handleBirthday(new BirthdayTreatIssued($c, $reward));
        $this->assertSame(['birthday'], $this->fcm->types());
        $this->assertSame('my_rewards', $this->fcm->sent[0]['data']['screen']);
        $this->assertSame('marketing', CustomerPushLog::first()->category);
        $this->assertStringContainsString('Your drink is on us this week.', $this->fcm->sent[0]['body']);
    }

    public function test_streak_almost_listener_includes_count(): void
    {
        $c = $this->customer();
        app(SendCustomerPushes::class)->handleStreakAlmost(new OrderStreakAlmostComplete($c, 6, 7));
        $this->assertStringContainsString('6 days in a row!', $this->fcm->sent[0]['body']);
        $this->assertSame('6', (string) $this->fcm->sent[0]['data']['count']);
    }

    // ---- order status texts (item 16) ----------------------------------

    public function test_order_status_texts_come_from_lang_with_original_wording(): void
    {
        $c = $this->customer();
        $order = \App\Core\Models\Order::factory()->create(['customer_id' => $c->id, 'status' => 'ready']);
        app(NotifyCustomerOnOrderStatusUpdate::class)->handle(new OrderStatusUpdated($order, 'mixing'));
        $this->assertSame('Your order is ready! • طلبك جاهز!', $this->fcm->sent[0]['title']);
        $this->assertSame('Head to the counter to pick it up. توجه إلى المنضدة لاستلام طلبك.', $this->fcm->sent[0]['body']);
        $this->assertSame('order_status', $this->fcm->sent[0]['data']['type']);
    }

    // ---- scheduled batch ----------------------------------------------

    protected function scheduledPush(string $audience = 'opted_in_only'): ScheduledPush
    {
        return ScheduledPush::create([
            'name' => 'Monday', 'title_en' => 'Monday', 'title_ar' => 'الاثنين', 'body_en' => 'Double points',
            'body_ar' => 'نقاط مضاعفة', 'time_of_day' => '09:00', 'timezone' => 'Africa/Cairo',
            'audience' => $audience, 'active' => true,
        ]);
    }

    protected function runBatch(ScheduledPush $push, array $customerIds): void
    {
        $send = \App\Core\Models\ScheduledPushSend::create([
            'scheduled_push_id' => $push->id, 'window_at' => now(), 'fired_at' => now(), 'recipients_count' => 0,
        ]);
        (new SendScheduledPushBatchJob($push->id, $send->id, [], $customerIds))
            ->handle($this->fcm, app(ScheduledPushService::class), $this->pushes());
        $this->send = $send->refresh();
    }

    public function test_batch_job_serialized_before_customer_ids_existed_still_runs(): void
    {
        $customer = $this->customer();
        $push = $this->scheduledPush();
        $send = \App\Core\Models\ScheduledPushSend::create([
            'scheduled_push_id' => $push->id, 'window_at' => now(), 'fired_at' => now(), 'recipients_count' => 0,
        ]);

        // Simulate a payload queued by the previous release: SerializesModels
        // leaves default-valued properties out, exactly like an old payload
        // that never had a customerIds key.
        $payload = serialize(new SendScheduledPushBatchJob($push->id, $send->id, [$customer->fcm_token], []));
        $this->assertStringNotContainsString('customerIds', $payload);

        $job = unserialize($payload);
        $this->assertSame([], $job->customerIds);
        $job->handle($this->fcm, app(ScheduledPushService::class), $this->pushes());

        $this->assertSame([$customer->fcm_token], array_column($this->fcm->sent, 'token'));
        $this->assertSame(1, $send->refresh()->sent_ok_count);
    }

    public function test_scheduled_batch_respects_daily_cap_and_logs(): void
    {
        $capped = $this->customer();
        $fresh = $this->customer();
        $this->at('2026-11-16 08:00');
        $this->pushes()->send($capped, 'gift_expiring');
        $this->fcm->sent = [];

        $this->at('2026-11-16 09:00');
        $this->runBatch($this->scheduledPush(), [$capped->id, $fresh->id]);

        $this->assertCount(1, $this->fcm->sent);
        $this->assertSame($fresh->fcm_token, $this->fcm->sent[0]['token']);
        $this->assertSame('scheduled_push', $this->fcm->sent[0]['data']['type']);
        $this->assertSame(1, $this->send->sent_ok_count);
        $this->assertSame('daily_cap', $this->log($capped, 'skipped')->first()->reason);
        $this->assertSame('marketing', $this->log($fresh, 'sent')->first()->category);
    }

    public function test_scheduled_batch_blocks_later_marketing_push_same_day(): void
    {
        $c = $this->customer();
        $this->at('2026-11-16 09:00');
        $this->runBatch($this->scheduledPush(), [$c->id]);
        $this->assertSame('daily_cap', $this->pushes()->send($c, 'big_win_entry')->reason);
    }

    public function test_scheduled_batch_enforces_opt_in_except_audience_all(): void
    {
        $out = $this->customer(['push_marketing_opt_in' => false]);
        $this->runBatch($this->scheduledPush('opted_in_only'), [$out->id]);
        $this->assertCount(0, $this->fcm->sent);
        $this->assertSame('not_opted_in', $this->log($out)->first()->reason);

        $this->runBatch($this->scheduledPush('all'), [$out->id]);
        $this->assertCount(1, $this->fcm->sent);
    }

    public function test_firing_a_scheduled_push_end_to_end_goes_through_the_cap(): void
    {
        $c = $this->customer();
        $this->at('2026-11-16 08:00');
        $this->pushes()->send($c, 'gift_expiring');
        $this->fcm->sent = [];
        $push = $this->scheduledPush();
        $this->at('2026-11-16 09:00');
        app(ScheduledPushService::class)->fireOne($push, \Carbon\CarbonImmutable::now('UTC'));
        $this->assertCount(0, $this->fcm->sent);
        $this->assertSame('daily_cap', $this->log($c, 'skipped')->first()->reason);
    }

    protected $send;
}
