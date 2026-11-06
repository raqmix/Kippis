<?php

namespace Tests\Unit;

use App\Jobs\PushOrderToFoodics;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FoodicsPhoneFieldsTest extends TestCase
{
    public static function cases(): array
    {
        return [
            'egypt local with trunk 0' => ['01001234567', '+20', ['dial_code' => 20, 'phone' => '1001234567']],
            'egypt without trunk 0'    => ['1001234567', '+20', ['dial_code' => 20, 'phone' => '1001234567']],
            'egypt typed with 20'      => ['201001234567', '+20', ['dial_code' => 20, 'phone' => '1001234567']],
            'egypt typed with 0020'    => ['00201001234567', '+20', ['dial_code' => 20, 'phone' => '1001234567']],
            'egypt typed with +20'     => ['+20 100 123 4567', '+20', ['dial_code' => 20, 'phone' => '1001234567']],
            'no country code defaults' => ['01001234567', null, ['dial_code' => 20, 'phone' => '1001234567']],
            'saudi'                    => ['0542125489', '+966', ['dial_code' => 966, 'phone' => '542125489']],
            'empty phone'              => ['', '+20', []],
            'null phone'               => [null, '+20', []],
        ];
    }

    #[DataProvider('cases')]
    public function test_splits_phone_for_foodics(?string $phone, ?string $cc, array $expected): void
    {
        $this->assertSame($expected, PushOrderToFoodics::foodicsPhoneFields($phone, $cc));
    }
}
