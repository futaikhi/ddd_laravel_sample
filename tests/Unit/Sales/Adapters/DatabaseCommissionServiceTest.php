<?php

declare(strict_types=1);

namespace Tests\Unit\Sales\Adapters;

use Illuminate\Support\Facades\Log;
use Src\Sales\Domain\Entities\Sale;
use Src\Sales\Domain\ValueObjects\CustomerId;
use Src\Sales\Domain\ValueObjects\LineItem;
use Src\Sales\Domain\ValueObjects\Money;
use Src\Sales\Domain\ValueObjects\ProductId;
use Src\Sales\Domain\ValueObjects\SaleId;
use Src\Sales\Infrastructure\Commission\DatabaseCommissionService;
use Tests\TestCase;

/**
 * GAP-002: verifies tiered commission logic mirrors the Task.txt business rules:
 *  - amount >= 1,000,000  => 5%
 *  - amount >= 500,000    => 3%
 *  - otherwise            => 1%
 */
final class DatabaseCommissionServiceTest extends TestCase
{
    private DatabaseCommissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();
        $this->service = new DatabaseCommissionService();
    }

    private function createSale(int $totalAmount): Sale
    {
        $lineItem = new LineItem(ProductId::random(), 1, new Money($totalAmount, 'IDR'));

        return Sale::create(SaleId::random(), CustomerId::random(), [$lineItem]);
    }

    public function test_it_applies_five_percent_when_amount_at_or_above_one_million(): void
    {
        $commission = $this->service->calculate($this->createSale(1_000_000));

        $this->assertSame(5.0, $commission->getRate());
        $this->assertSame(50_000, $commission->getAmount()->getValue());
    }

    public function test_it_applies_five_percent_for_amounts_much_greater_than_one_million(): void
    {
        $commission = $this->service->calculate($this->createSale(2_500_000));

        $this->assertSame(5.0, $commission->getRate());
        $this->assertSame(125_000, $commission->getAmount()->getValue());
    }

    public function test_it_applies_three_percent_at_five_hundred_thousand_boundary(): void
    {
        $commission = $this->service->calculate($this->createSale(500_000));

        $this->assertSame(3.0, $commission->getRate());
        $this->assertSame(15_000, $commission->getAmount()->getValue());
    }

    public function test_it_applies_three_percent_for_amounts_between_500k_and_one_million(): void
    {
        $commission = $this->service->calculate($this->createSale(999_999));

        $this->assertSame(3.0, $commission->getRate());
    }

    public function test_it_applies_one_percent_below_five_hundred_thousand(): void
    {
        $commission = $this->service->calculate($this->createSale(499_999));

        $this->assertSame(1.0, $commission->getRate());
    }

    public function test_it_applies_one_percent_for_small_sale(): void
    {
        $commission = $this->service->calculate($this->createSale(50_000));

        $this->assertSame(1.0, $commission->getRate());
        $this->assertSame(500, $commission->getAmount()->getValue());
    }
}
