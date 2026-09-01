<?php

declare(strict_types=1);

namespace Tests\Unit\Sales\Adapters;

use PHPUnit\Framework\TestCase;
use Src\Sales\Domain\Entities\Sale;
use Src\Sales\Domain\ValueObjects\CustomerId;
use Src\Sales\Domain\ValueObjects\LineItem;
use Src\Sales\Domain\ValueObjects\Money;
use Src\Sales\Domain\ValueObjects\ProductId;
use Src\Sales\Domain\ValueObjects\SaleId;
use Src\Sales\Infrastructure\Commission\MockCommissionService;

class MockCommissionServiceTest extends TestCase
{
    private MockCommissionService $service;

    protected function setUp(): void
    {
        $this->service = new MockCommissionService();
    }

    private function createSale(int $totalAmount): Sale
    {
        $saleId = SaleId::random();
        $customerId = CustomerId::random();
        $lineItem = new LineItem(ProductId::random(), 1, new Money($totalAmount, 'IDR'));

        return Sale::create($saleId, $customerId, [$lineItem]);
    }

    public function test_default_fixed_rate_is_3_percent(): void
    {
        $sale = $this->createSale(100000);

        $commission = $this->service->calculate($sale);

        $this->assertEquals(3.0, $commission->getRate());
        $this->assertEquals(3000, $commission->getAmount()->getValue());
    }

    public function test_can_set_custom_fixed_rate(): void
    {
        $sale = $this->createSale(100000);
        $this->service->setFixedRate(5.0);

        $commission = $this->service->calculate($sale);

        $this->assertEquals(5.0, $commission->getRate());
        $this->assertEquals(5000, $commission->getAmount()->getValue());
    }

    public function test_zero_rate_commission(): void
    {
        $sale = $this->createSale(100000);
        $this->service->setFixedRate(0.0);

        $commission = $this->service->calculate($sale);

        $this->assertEquals(0.0, $commission->getRate());
        $this->assertEquals(0, $commission->getAmount()->getValue());
    }

    public function test_max_rate_commission(): void
    {
        $sale = $this->createSale(100000);
        $this->service->setFixedRate(100.0);

        $commission = $this->service->calculate($sale);

        $this->assertEquals(100.0, $commission->getRate());
        $this->assertEquals(100000, $commission->getAmount()->getValue());
    }

    public function test_invalid_rate_below_zero_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Commission rate must be between 0 and 100');

        $this->service->setFixedRate(-1.0);
    }

    public function test_invalid_rate_above_hundred_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Commission rate must be between 0 and 100');

        $this->service->setFixedRate(101.0);
    }

    public function test_commission_calculation_for_different_amounts(): void
    {
        $this->service->setFixedRate(10.0);

        // 50000 * 10% = 5000
        $sale1 = $this->createSale(50000);
        $commission1 = $this->service->calculate($sale1);
        $this->assertEquals(5000, $commission1->getAmount()->getValue());

        // 1000000 * 10% = 100000
        $sale2 = $this->createSale(1000000);
        $commission2 = $this->service->calculate($sale2);
        $this->assertEquals(100000, $commission2->getAmount()->getValue());
    }

    public function test_reset_restores_default_rate(): void
    {
        $sale = $this->createSale(100000);

        $this->service->setFixedRate(10.0);
        $commission1 = $this->service->calculate($sale);
        $this->assertEquals(10.0, $commission1->getRate());

        $this->service->reset();
        $commission2 = $this->service->calculate($sale);
        $this->assertEquals(3.0, $commission2->getRate());
    }
}
