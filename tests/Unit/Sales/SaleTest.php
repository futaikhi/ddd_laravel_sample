<?php

declare(strict_types=1);

namespace Tests\Unit\Sales;

use PHPUnit\Framework\TestCase;
use Src\Sales\Domain\Entities\Sale;
use Src\Sales\Domain\Enums\OrderStatus;
use Src\Sales\Domain\Exceptions\MinimumOrderAmountException;
use Src\Sales\Domain\Exceptions\SaleCannotBeCancelledException;
use Src\Sales\Domain\Exceptions\SaleCannotBeCompletedException;
use Src\Sales\Domain\Exceptions\SaleCannotBeConfirmedException;
use Src\Sales\Domain\ValueObjects\CustomerId;
use Src\Sales\Domain\ValueObjects\LineItem;
use Src\Sales\Domain\ValueObjects\Money;
use Src\Sales\Domain\ValueObjects\SaleId;

final class SaleTest extends TestCase
{
    public function test_it_creates_a_valid_sale(): void
    {
        $sale = Sale::create(
            SaleId::random(),
            CustomerId::random(),
            [
                new LineItem('prod-1', 2, Money::fromCents(25000, 'IDR')),
                new LineItem('prod-2', 1, Money::fromCents(30000, 'IDR')),
            ],
        );

        $this->assertSame(OrderStatus::PENDING, $sale->getStatus());
        $this->assertSame(80000, $sale->getTotalAmount()->getValue());
        $this->assertCount(1, $sale->releaseEvents());
    }

    public function test_it_rejects_sales_below_minimum_order_amount(): void
    {
        $this->expectException(MinimumOrderAmountException::class);

        Sale::create(
            SaleId::random(),
            CustomerId::random(),
            [
                new LineItem('prod-1', 1, Money::fromCents(20000, 'IDR')),
            ],
        );
    }

    public function test_it_can_confirm_a_pending_sale(): void
    {
        $sale = Sale::create(
            SaleId::random(),
            CustomerId::random(),
            [
                new LineItem('prod-1', 2, Money::fromCents(30000, 'IDR')),
            ],
        );

        $sale->confirm();

        $this->assertSame(OrderStatus::CONFIRMED, $sale->getStatus());
        $this->assertCount(2, $sale->releaseEvents());
    }

    public function test_it_cannot_confirm_a_completed_sale(): void
    {
        $this->expectException(SaleCannotBeConfirmedException::class);

        $sale = Sale::create(
            SaleId::random(),
            CustomerId::random(),
            [
                new LineItem('prod-1', 2, Money::fromCents(30000, 'IDR')),
            ],
        );

        $sale->confirm();
        $sale->complete();
        $sale->confirm();
    }

    public function test_it_can_complete_a_confirmed_sale(): void
    {
        $sale = Sale::create(
            SaleId::random(),
            CustomerId::random(),
            [
                new LineItem('prod-1', 2, Money::fromCents(30000, 'IDR')),
            ],
        );

        $sale->confirm();
        $sale->complete();

        $this->assertSame(OrderStatus::COMPLETED, $sale->getStatus());
    }

    public function test_it_cannot_complete_a_non_confirmed_sale(): void
    {
        $this->expectException(SaleCannotBeCompletedException::class);

        $sale = Sale::create(
            SaleId::random(),
            CustomerId::random(),
            [
                new LineItem('prod-1', 2, Money::fromCents(30000, 'IDR')),
            ],
        );

        $sale->complete();
    }

    public function test_it_can_cancel_a_pending_sale(): void
    {
        $sale = Sale::create(
            SaleId::random(),
            CustomerId::random(),
            [
                new LineItem('prod-1', 2, Money::fromCents(30000, 'IDR')),
            ],
        );

        $sale->cancel('customer changed mind');

        $this->assertSame(OrderStatus::CANCELLED, $sale->getStatus());
        $this->assertSame('customer changed mind', $sale->getCancellationReason());
    }

    public function test_it_cannot_cancel_an_already_cancelled_sale(): void
    {
        $this->expectException(SaleCannotBeCancelledException::class);

        $sale = Sale::create(
            SaleId::random(),
            CustomerId::random(),
            [
                new LineItem('prod-1', 2, Money::fromCents(30000, 'IDR')),
            ],
        );

        $sale->cancel('first cancellation');
        $sale->cancel('second cancellation');
    }
}
