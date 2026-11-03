<?php

namespace Tests\Feature;

use App\Core\Models\Store;
use App\Integrations\Foodics\Services\FoodicsAuthService;
use App\Modules\Stores\Services\FoodicsBranchesSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Guards the branch sync against overwriting what admins set in Kippis.
 * Real Foodics branches carry no location, 00:00-00:00 hours and no
 * is_active field; the old sync turned those into nulls and `true`.
 */
class FoodicsBranchesSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['foodics.mode' => 'live']);

        $auth = $this->createMock(FoodicsAuthService::class);
        $auth->method('getAccessToken')->willReturn('test-token');
        $this->app->instance(FoodicsAuthService::class, $auth);

        // The full migration set uses MySQL-only DDL (ALTER ... MODIFY) and
        // can't run on the sqlite test DB, so build just the table under test.
        Schema::dropIfExists('stores');
        Schema::create('stores', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->json('name_localized')->nullable();
            $t->text('address')->nullable();
            $t->decimal('latitude', 10, 8)->nullable();
            $t->decimal('longitude', 11, 8)->nullable();
            $t->string('phone')->nullable();
            $t->time('open_time')->nullable();
            $t->time('close_time')->nullable();
            $t->boolean('is_active')->default(true);
            $t->boolean('receive_online_orders')->default(true);
            $t->boolean('is_employee_only')->default(false);
            $t->boolean('is_proximity_only')->default(false);
            $t->integer('proximity_radius_meters')->nullable();
            $t->string('foodics_branch_id')->nullable()->unique();
            $t->string('foodics_menu_group_id')->nullable();
            $t->timestamp('synced_from_foodics_at')->nullable();
            $t->string('kiosk_api_key')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
    }

    private function fakeBranches(array $branches): void
    {
        Http::fake(['*v5/branches*' => Http::response([
            'data' => $branches,
            'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 50, 'total' => count($branches)],
        ])]);
    }

    private function branch(array $overrides = []): array
    {
        // Shape of a real v5 branch (Smash, 2026-11-03).
        return $overrides + [
            'id' => 'b-smash',
            'name' => 'Smash',
            'name_localized' => null,
            'reference' => 'B10',
            'receives_online_orders' => true,
            'latitude' => null,
            'longitude' => null,
            'address' => null,
            'phone' => null,
            'opening_from' => '00:00',
            'opening_to' => '00:00',
            'deleted_at' => null,
        ];
    }

    private function sync(): array
    {
        return app(FoodicsBranchesSyncService::class)->syncAllBranches();
    }

    public function test_existing_store_keeps_admin_location_hours_visibility_and_arabic_name(): void
    {
        $store = Store::create([
            'name' => 'Events 2',
            'name_localized' => ['en' => 'Smash', 'ar' => 'سماش'],
            'latitude' => 30.119045,
            'longitude' => 31.400633,
            'open_time' => '07:30:00',
            'close_time' => '22:30:00',
            'is_active' => false,
            'receive_online_orders' => false,
            'foodics_branch_id' => 'b-smash',
        ]);

        $this->fakeBranches([$this->branch()]);
        $this->sync();

        $store->refresh();
        $this->assertSame('Smash', $store->name);                 // rename in Foodics still flows through
        $this->assertSame('30.11904500', $store->latitude);
        $this->assertSame('31.40063300', $store->longitude);
        $this->assertSame('07:30:00', $store->open_time);
        $this->assertSame('22:30:00', $store->close_time);
        $this->assertSame('سماش', $store->name_localized['ar']);
        $this->assertFalse($store->is_active);                    // not switched back on
        $this->assertFalse($store->receive_online_orders);
        $this->assertNotNull($store->synced_from_foodics_at);
    }

    public function test_real_foodics_values_still_update_the_store(): void
    {
        $store = Store::create([
            'name' => 'Smash',
            'is_active' => true,
            'receive_online_orders' => true,
            'foodics_branch_id' => 'b-smash',
        ]);

        $this->fakeBranches([$this->branch([
            'latitude' => '30.5',
            'longitude' => '31.5',
            'opening_from' => '08:00',
            'opening_to' => '23:00',
            'name_localized' => 'سماش',
        ])]);
        $this->sync();

        $store->refresh();
        $this->assertSame('30.50000000', $store->latitude);
        $this->assertSame('08:00', substr($store->open_time, 0, 5));
        $this->assertSame('23:00', substr($store->close_time, 0, 5));
        $this->assertSame(['en' => 'Smash', 'ar' => 'سماش'], $store->name_localized);
        $this->assertTrue($store->is_active);
    }

    public function test_branches_not_receiving_online_orders_are_skipped(): void
    {
        $this->fakeBranches([
            $this->branch(['id' => 'b-factory', 'name' => 'Factory', 'receives_online_orders' => false]),
            $this->branch(['id' => 'b-smash']),
        ]);

        $stats = $this->sync();

        $this->assertSame(1, $stats['skipped']);
        $this->assertFalse(Store::where('foodics_branch_id', 'b-factory')->exists());
        $this->assertTrue(Store::where('foodics_branch_id', 'b-smash')->exists());
    }

    public function test_new_branch_is_created_hidden(): void
    {
        $this->fakeBranches([$this->branch()]);

        $stats = $this->sync();

        $store = Store::where('foodics_branch_id', 'b-smash')->firstOrFail();
        $this->assertSame(1, $stats['created']);
        $this->assertSame('Smash', $store->name);
        $this->assertFalse($store->is_active);
        $this->assertFalse($store->receive_online_orders);
        $this->assertNull($store->open_time);
    }
}
