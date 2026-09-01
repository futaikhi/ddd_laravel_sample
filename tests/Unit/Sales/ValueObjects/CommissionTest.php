<?php

declare(strict_types=1);

namespace Tests\Unit\Sales\ValueObjects;

use PHPUnit\Framework\TestCase;
use Src\Sales\Domain\ValueObjects\Commission;
use Src\Sales\Domain\ValueObjects\Money;

class CommissionTest extends TestCase
{
    public function test_create_commission_with_explicit_amount_and_rate(): void
    {
        $amount = new Money(5000, 'IDR');
        $commission = new Commission($amount, 5.0, 'Standard 5% commission');

        $this->assertEquals($amount, $commission->getAmount());
        $this->assertEquals(5.0, $commission->getRate());
        $this->assertEquals('Standard 5% commission', $commission->getDescription());
    }

    public function test_create_commission_from_rate(): void
    {
        $baseAmount = new Money(100000, 'IDR');
        $commission = Commission::fromRate($baseAmount, 5.0, '5% commission');

        // 100000 * 5% = 5000
        $this->assertEquals(5000, $commission->getAmount()->getValue());
        $this->assertEquals(5.0, $commission->getRate());
        $this->assertEquals('5% commission', $commission->getDescription());
    }

    public function test_commission_rate_calculation_with_different_rates(): void
    {
        $baseAmount = new Money(1000000, 'IDR'); // 1 million

        // 3% commission
        $commission3 = Commission::fromRate($baseAmount, 3.0);
        $this->assertEquals(30000, $commission3->getAmount()->getValue());

        // 5% commission
        $commission5 = Commission::fromRate($baseAmount, 5.0);
        $this->assertEquals(50000, $commission5->getAmount()->getValue());

        // 0% commission
        $commission0 = Commission::fromRate($baseAmount, 0.0);
        $this->assertEquals(0, $commission0->getAmount()->getValue());
    }

    public function test_invalid_rate_below_zero_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Commission rate must be between 0 and 100');

        new Commission(new Money(5000, 'IDR'), -1.0);
    }

    public function test_invalid_rate_above_hundred_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Commission rate must be between 0 and 100');

        new Commission(new Money(5000, 'IDR'), 101.0);
    }

    public function test_from_rate_with_invalid_rate_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Commission::fromRate(new Money(100000, 'IDR'), 150.0);
    }

    public function test_commission_with_default_description(): void
    {
        $amount = new Money(10000, 'IDR');
        $commission = new Commission($amount, 10.0);

        $this->assertEquals('', $commission->getDescription());
    }

    public function test_commission_rate_rounding(): void
    {
        $baseAmount = new Money(333, 'IDR');

        // 333 * 3% = 9.99, should round to 10
        $commission = Commission::fromRate($baseAmount, 3.0);
        $this->assertEquals(10, $commission->getAmount()->getValue());
    }
}
