<?php

namespace Tests\Unit;

use App\Core\Services\FoodicsSyncService;
use PHPUnit\Framework\TestCase;

class FoodicsBranchAvailabilityTest extends TestCase
{
    private function product(array $branches): array
    {
        return ['id' => 'p1', 'branches' => $branches];
    }

    private function branch(string $id, array $pivot): array
    {
        return ['id' => $id, 'pivot' => $pivot + ['price' => null, 'is_active' => true, 'is_in_stock' => true]];
    }

    public function test_disabled_in_branch_is_unavailable(): void
    {
        $p = $this->product([$this->branch('yard', ['is_active' => false]), $this->branch('park', [])]);
        $this->assertFalse(FoodicsSyncService::isAvailableAtBranch($p, 'yard'));
        $this->assertTrue(FoodicsSyncService::isAvailableAtBranch($p, 'park'));
    }

    public function test_out_of_stock_in_branch_is_unavailable(): void
    {
        $p = $this->product([$this->branch('yard', ['is_in_stock' => false])]);
        $this->assertFalse(FoodicsSyncService::isAvailableAtBranch($p, 'yard'));
    }

    public function test_integer_flags_are_understood(): void
    {
        $p = $this->product([$this->branch('yard', ['is_active' => 0])]);
        $this->assertFalse(FoodicsSyncService::isAvailableAtBranch($p, 'yard'));
    }

    public function test_no_branch_entry_or_no_data_means_available(): void
    {
        $this->assertTrue(FoodicsSyncService::isAvailableAtBranch($this->product([$this->branch('park', ['is_active' => false])]), 'yard'));
        $this->assertTrue(FoodicsSyncService::isAvailableAtBranch(['id' => 'p1'], 'yard'));
        $this->assertTrue(FoodicsSyncService::isAvailableAtBranch($this->product([]), null));
    }
}
