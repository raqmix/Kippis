<?php

namespace Tests\Feature;

use App\Core\Models\Customer;
use App\Core\Models\CustomerRedeemWallet;
use App\Core\Models\Setting;
use App\Events\BirthdayTreatIssued;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/** Brief section D: weekly birthday treat. */
class BirthdayTreatTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function now(string $cairo): void
    {
        Carbon::setTestNow(Carbon::parse($cairo, 'Africa/Cairo'));
    }

    private function run8am(): void
    {
        $this->artisan('rewards:issue-birthday-treats')->assertExitCode(0);
    }

    private function rewards(Customer $c)
    {
        return CustomerRedeemWallet::where('customer_id', $c->id)->where('source', 'birthday')->get();
    }

    public function test_issues_for_birthday_in_current_week_only(): void
    {
        Event::fake([BirthdayTreatIssued::class]);
        $this->now('2026-11-18 08:00'); // Wed; week Mon 16 - Sun 22
        $early = Customer::factory()->create(['birthdate' => '1990-11-16']);
        $late = Customer::factory()->create(['birthdate' => '2000-11-22']);
        $next = Customer::factory()->create(['birthdate' => '1990-11-23']);
        $prev = Customer::factory()->create(['birthdate' => '1990-11-15']);
        $none = Customer::factory()->create(['birthdate' => null]);

        $this->run8am();

        $this->assertCount(1, $this->rewards($early));
        $this->assertCount(1, $this->rewards($late));
        foreach ([$next, $prev, $none] as $c) {
            $this->assertCount(0, $this->rewards($c));
        }
        Event::assertDispatchedTimes(BirthdayTreatIssued::class, 2);
    }

    public function test_reward_expires_sunday_end_of_day_cairo_and_uses_preset(): void
    {
        Setting::set('rewards.birthday_drink.category_ids', [5, 6], 'json', 'rewards');
        Setting::set('rewards.birthday_drink.max_value', 99, 'number', 'rewards');
        $this->now('2026-11-18 08:00');
        $c = Customer::factory()->create(['birthdate' => '1990-11-18']);

        $this->run8am();

        $reward = $this->rewards($c)->first();
        $this->assertSame('birthday:2026', $reward->source_ref);
        $this->assertSame('free_item', $reward->reward_type);
        $this->assertSame([5, 6], $reward->reward_config['category_ids']);
        $this->assertEquals(99.0, $reward->reward_config['max_value']);
        $this->assertSame('2026-11-22 23:59:59', $reward->expires_at->setTimezone('Africa/Cairo')->format('Y-m-d H:i:s'));
    }

    public function test_once_per_year_and_event_fired_once(): void
    {
        Event::fake([BirthdayTreatIssued::class]);
        $this->now('2026-11-18 08:00');
        $c = Customer::factory()->create(['birthdate' => '1990-11-18']);

        $this->run8am();
        $this->now('2026-11-19 08:00');
        $this->run8am();
        $this->now('2026-11-22 08:00');
        $this->run8am();

        $this->assertCount(1, $this->rewards($c));
        Event::assertDispatchedTimes(BirthdayTreatIssued::class, 1);

        // Next year a new one.
        $this->now('2027-11-17 08:00');
        $this->run8am();
        $this->assertCount(2, $this->rewards($c));
    }

    public function test_feb_29_is_celebrated_feb_28_in_non_leap_years(): void
    {
        $c = Customer::factory()->create(['birthdate' => '1996-02-29']);

        $this->now('2027-02-24 08:00'); // Wed; week Mon 22 - Sun 28 Feb 2027
        $this->run8am();
        $this->assertCount(1, $this->rewards($c));

        // Leap year: on Feb 29 itself (week Mon 26 Feb - Sun 3 Mar 2028), once.
        $this->now('2028-02-28 08:00');
        $this->run8am();
        $this->assertCount(2, $this->rewards($c));
    }

    public function test_feb_29_not_issued_in_other_weeks(): void
    {
        $c = Customer::factory()->create(['birthdate' => '1996-02-29']);
        $this->now('2027-03-03 08:00');
        $this->run8am();
        $this->assertCount(0, $this->rewards($c));
    }

    public function test_birthdate_added_mid_week_gets_treat_on_next_run(): void
    {
        Event::fake([BirthdayTreatIssued::class]);
        $this->now('2026-11-18 08:00');
        $c = Customer::factory()->create(['birthdate' => null]);
        $this->run8am();
        $this->assertCount(0, $this->rewards($c));

        $this->actingAs($c, 'api')->putJson('/api/v1/auth/me', ['birthdate' => '1992-11-17'])->assertOk();

        $this->now('2026-11-19 08:00');
        $this->run8am();
        $this->assertCount(1, $this->rewards($c));
        Event::assertDispatchedTimes(BirthdayTreatIssued::class, 1);
    }

    public function test_week_spanning_new_year_keys_by_birthday_year(): void
    {
        $this->now('2026-12-30 08:00'); // week Mon 28 Dec - Sun 3 Jan
        $c = Customer::factory()->create(['birthdate' => '1990-01-02']);
        $this->run8am();
        $this->assertSame('birthday:2027', $this->rewards($c)->first()->source_ref);
    }

    public function test_disabled_setting_issues_nothing(): void
    {
        Setting::set('birthday_treat.enabled', false, 'boolean', 'rewards');
        $this->now('2026-11-18 08:00');
        $c = Customer::factory()->create(['birthdate' => '1990-11-18']);
        $this->run8am();
        $this->assertCount(0, $this->rewards($c));
    }

    public function test_cairo_day_not_utc_day_decides_the_week(): void
    {
        // Sunday 23:30 Cairo is still Sunday; Monday 00:30 Cairo (Sun 22:30 UTC) is already next week.
        $c = Customer::factory()->create(['birthdate' => '1990-11-23']);
        $this->now('2026-11-23 00:30'); // Mon Cairo, still Sun in UTC
        $this->run8am();
        $this->assertCount(1, $this->rewards($c));
    }
}
