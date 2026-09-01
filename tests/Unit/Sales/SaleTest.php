<?php

declare(strict_types=1);

namespace Tests\Unit\Sales;

use PHPUnit\Framework\TestCase;
use Src\Sales\Domain\Entities\Sale;
use Src\Sales\Domain\Enums\OrderStatus;
use Src\Sales\Domain\Enums\PaymentMethod;
use Src\Sales\Domain\Exceptions\MinimumOrderAmountException;
use Src\Sales\Domain\Exceptions\SaleCannotBeCancelledException;
use Src\Sales\Domain\Exceptions\SaleCannotBeCompletedException;
use Src\Sales\Domain\Exceptions\SaleCannotBeConfirmedException;
use Src\Sales\Domain\ValueObjects\Commission;
use Src\Sales\Domain\ValueObjects\CustomerId;
use Src\Sales\Domain\ValueObjects\LineItem;
use Src\Sales\Domain\ValueObjects\Money;
use Src\Sales\Domain\ValueObjects\ProductId;
use Src\Sales\Domain\ValueObjects\SaleId;

final class SaleTest extends TestCase
{
    public function test_it_creates_a_valid_sale(): void
    {
        $sale = Sale::create(
            SaleId::random(),
            CustomerId::random(),
            [
                new LineItem(ProductId::fromString('01H8M6KJ5NQ8XX4P0N2VYJ4K5A'), 2, Money::fromCents(25000, 'IDR')),
                new LineItem(ProductId::fromString('01H8M6KJ5NQ8XX4P0N2VYJ4K5B'), 1, Money::fromCents(30000, 'IDR')),
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
                new LineItem(ProductId::fromString('01H8M6KJ5NQ8XX4P0N2VYJ4K5C'), 1, Money::fromCents(20000, 'IDR')),
            ],
        );
    }

    public function test_it_rejects_line_items_with_zero_quantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new LineItem(
            ProductId::fromString('01H8M6KJ5NQ8XX4P0N2VYJ4K5D'),
            0,
            Money::fromCents(30000, 'IDR')
        );
    }

    public function test_it_rejects_sales_with_more_than_twenty_line_items(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $items = [];
        for ($i = 0; $i < 21; $i++) {
            $items[] = new LineItem(
                ProductId::fromString('01H8M6KJ5NQ8XX4P0N2VYJ4K'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)),
                1,
                Money::fromCents(30000, 'IDR')
            );
        }

        Sale::create(
            SaleId::random(),
            CustomerId::random(),
            $items,
        );
    }

    public function test_it_can_confirm_a_pending_sale(): void
    {
        $sale = Sale::create(
            SaleId::random(),
            CustomerId::random(),
            [
                new LineItem(ProductId::fromString('01H8M6KJ5NQ8XX4P0N2VYJ4K5D'), 2, Money::fromCents(30000, 'IDR')),
            ],
        );

        $sale->confirm(PaymentMethod::CASH, 'TXN-TEST-001');

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
                new LineItem(ProductId::fromString('01H8M6KJ5NQ8XX4P0N2VYJ4K5D'), 2, Money::fromCents(30000, 'IDR')),
            ],
        );

        $sale->confirm(PaymentMethod::CASH, 'TXN-TEST-001');
        $sale->complete(Commission::fromRate($sale->getTotalAmount(), 3.0));
        $sale->confirm(PaymentMethod::CASH, 'TXN-TEST-001');
    }

    public function test_it_can_complete_a_confirmed_sale(): void
    {
        $sale = Sale::create(
            SaleId::random(),
            CustomerId::random(),
            [
                new LineItem(ProductId::fromString('01H8M6KJ5NQ8XX4P0N2VYJ4K5D'), 2, Money::fromCents(30000, 'IDR')),
            ],
        );

        $sale->confirm(PaymentMethod::CASH, 'TXN-TEST-001');
        $sale->complete(Commission::fromRate($sale->getTotalAmount(), 3.0));

        $this->assertSame(OrderStatus::COMPLETED, $sale->getStatus());
    }

    public function test_it_cannot_complete_a_pending_sale_directly(): void
    {
        $this->expectException(SaleCannotBeCompletedException::class);

        $sale = Sale::create(
            SaleId::random(),
            CustomerId::random(),
            [
                new LineItem(ProductId::fromString('01H8M6KJ5NQ8XX4P0N2VYJ4K5D'), 2, Money::fromCents(30000, 'IDR')),
            ],
        );

        $sale->complete(Commission::fromRate($sale->getTotalAmount(), 3.0));
    }

    public function test_it_cannot_reopen_a_completed_sale(): void
    {
        $this->expectException(SaleCannotBeConfirmedException::class);

        $sale = Sale::create(
            SaleId::random(),
            CustomerId::random(),
            [
                new LineItem(ProductId::fromString('01H8M6KJ5NQ8XX4P0N2VYJ4K5D'), 2, Money::fromCents(30000, 'IDR')),
            ],
        );

        $sale->confirm(PaymentMethod::CASH, 'TXN-TEST-001');
        $sale->complete(Commission::fromRate($sale->getTotalAmount(), 3.0));
        $sale->confirm(PaymentMethod::CASH, 'TXN-TEST-001');
    }

    public function test_it_cannot_reopen_a_cancelled_sale(): void
    {
        $this->expectException(SaleCannotBeCancelledException::class);

        $sale = Sale::create(
            SaleId::random(),
            CustomerId::random(),
            [
                new LineItem(ProductId::fromString('01H8M6KJ5NQ8XX4P0N2VYJ4K5D'), 2, Money::fromCents(30000, 'IDR')),
            ],
        );

        $sale->cancel('customer changed mind');
        $sale->cancel('try again');
    }

    public function test_it_cannot_complete_a_non_confirmed_sale(): void
    {
        $this->expectException(SaleCannotBeCompletedException::class);

        $sale = Sale::create(
            SaleId::random(),
            CustomerId::random(),
            [
                new LineItem(ProductId::fromString('01H8M6KJ5NQ8XX4P0N2VYJ4K5D'), 2, Money::fromCents(30000, 'IDR')),
            ],
        );

        $sale->complete(Commission::fromRate($sale->getTotalAmount(), 3.0));
    }

    public function test_it_can_cancel_a_pending_sale(): void
    {
        $sale = Sale::create(
            SaleId::random(),
            CustomerId::random(),
            [
                new LineItem(ProductId::fromString('01H8M6KJ5NQ8XX4P0N2VYJ4K5D'), 2, Money::fromCents(30000, 'IDR')),
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
                new LineItem(ProductId::fromString('01H8M6KJ5NQ8XX4P0N2VYJ4K5D'), 2, Money::fromCents(30000, 'IDR')),
            ],
        );

        $sale->cancel('first cancellation');
        $sale->cancel('second cancellation');
    }

    public function test_it_tracks_created_at_on_creation(): void
    {
        $sale = Sale::create(
            SaleId::random(),
            CustomerId::random(),
            [
                new LineItem(ProductId::fromString('01H8M6KJ5NQ8XX4P0N2VYJ4K5D'), 2, Money::fromCents(30000, 'IDR')),
            ],
        );

        $this->assertInstanceOf(\DateTimeImmutable::class, $sale->getCreatedAt());
        $this->assertNotNull($sale->getCreatedAt());
    }
}
