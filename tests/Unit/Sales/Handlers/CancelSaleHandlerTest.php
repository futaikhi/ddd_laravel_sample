<?php

declare(strict_types=1);

namespace Tests\Unit\Sales\Handlers;

use PHPUnit\Framework\TestCase;
use Src\Sales\Application\Commands\Cancel\CancelSaleCommand;
use Src\Sales\Application\Commands\Cancel\CancelSaleHandler;
use Src\Sales\Domain\Entities\Sale;
use Src\Sales\Domain\Enums\OrderStatus;
use Src\Sales\Domain\Enums\PaymentMethod;
use Src\Sales\Domain\Exceptions\SaleCannotBeCancelledException;
use Src\Sales\Domain\Ports\PaymentFailedException;
use Src\Sales\Domain\Ports\PaymentGatewayInterface;
use Src\Sales\Domain\Repositories\SaleRepositoryInterface;
use Src\Sales\Domain\ValueObjects\CustomerId;
use Src\Sales\Domain\ValueObjects\LineItem;
use Src\Sales\Domain\ValueObjects\Money;
use Src\Sales\Domain\ValueObjects\PaymentRequest;
use Src\Sales\Domain\ValueObjects\PaymentResult;
use Src\Sales\Domain\ValueObjects\ProductId;
use Src\Sales\Domain\ValueObjects\SaleId;
use Src\Shared\Framework\Infrastructure\Bus\EventBus\EventBusInterface;

final class CancelSaleHandlerTest extends TestCase
{
    public function test_it_cancels_pending_sale_without_refund(): void
    {
        $sale = $this->makeSale();
        $repo = $this->makeRepo($sale);
        $paymentGateway = $this->createMock(PaymentGatewayInterface::class);
        $paymentGateway->expects($this->never())->method('refund');
        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects($this->once())->method('publishEvents');

        $handler = new CancelSaleHandler($repo, $paymentGateway, $eventBus);
        $handler(new CancelSaleCommand($sale->getId(), 'customer changed mind'));

        $this->assertSame(OrderStatus::CANCELLED, $sale->getStatus());
        $this->assertSame('customer changed mind', $sale->getCancellationReason());
        $this->assertTrue($repo->stored);
    }

    public function test_it_refunds_confirmed_sale_before_cancellation(): void
    {
        $sale = $this->makeSale();
        $sale->confirm(PaymentMethod::CREDIT_CARD, 'TXN-CONFIRMED-001');
        $repo = $this->makeRepo($sale);
        $paymentGateway = $this->createMock(PaymentGatewayInterface::class);
        $paymentGateway->expects($this->once())
            ->method('refund')
            ->with('TXN-CONFIRMED-001');
        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects($this->once())->method('publishEvents');

        $handler = new CancelSaleHandler($repo, $paymentGateway, $eventBus);
        $handler(new CancelSaleCommand($sale->getId(), 'customer requested refund'));

        $this->assertSame(OrderStatus::CANCELLED, $sale->getStatus());
        $this->assertSame('customer requested refund', $sale->getCancellationReason());
        $this->assertTrue($repo->stored);
    }

    public function test_refund_failure_keeps_confirmed_sale_unchanged(): void
    {
        $sale = $this->makeSale();
        $sale->confirm(PaymentMethod::CREDIT_CARD, 'TXN-CONFIRMED-002');
        $repo = $this->makeRepo($sale, expectStore: false);
        $paymentGateway = $this->createMock(PaymentGatewayInterface::class);
        $paymentGateway->expects($this->once())
            ->method('refund')
            ->with('TXN-CONFIRMED-002')
            ->willThrowException(PaymentFailedException::withMessage('Refund failed'));
        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects($this->never())->method('publishEvents');

        $handler = new CancelSaleHandler($repo, $paymentGateway, $eventBus);

        try {
            $handler(new CancelSaleCommand($sale->getId(), 'customer requested refund'));
            $this->fail('Expected PaymentFailedException');
        } catch (PaymentFailedException $e) {
            $this->assertSame('Refund failed', $e->getMessage());
        }

        $this->assertSame(OrderStatus::CONFIRMED, $sale->getStatus());
        $this->assertFalse($repo->stored);
    }

    public function test_confirmed_sale_without_transaction_id_cannot_be_cancelled(): void
    {
        $sale = Sale::reconstitute(
            id: SaleId::random(),
            customerId: CustomerId::random(),
            lineItems: [new LineItem(ProductId::fromString('01H8M6KJ5NQ8XX4P0N2VYJ4K5D'), 2, Money::fromCents(30000, 'IDR'))],
            status: OrderStatus::CONFIRMED,
            totalAmount: Money::fromCents(60000, 'IDR'),
            createdAt: new \DateTimeImmutable(),
            paymentMethod: PaymentMethod::CREDIT_CARD,
            transactionId: null,
        );

        $repo = $this->makeRepo($sale, expectStore: false);
        $paymentGateway = $this->createMock(PaymentGatewayInterface::class);
        $paymentGateway->expects($this->never())->method('refund');
        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects($this->never())->method('publishEvents');

        $handler = new CancelSaleHandler($repo, $paymentGateway, $eventBus);

        $this->expectException(SaleCannotBeCancelledException::class);
        $this->expectExceptionMessage('payment transaction id is missing');

        $handler(new CancelSaleCommand($sale->getId(), 'customer requested refund'));
    }

    private function makeSale(): Sale
    {
        return Sale::create(
            SaleId::random(),
            CustomerId::random(),
            [new LineItem(ProductId::fromString('01H8M6KJ5NQ8XX4P0N2VYJ4K5D'), 2, Money::fromCents(30000, 'IDR'))],
        );
    }

    private function makeRepo(Sale $sale, bool $expectStore = true): SaleRepositoryInterface
    {
        return new class ($sale, $expectStore) implements SaleRepositoryInterface {
            public bool $stored = false;

            public function __construct(private Sale $sale, private bool $expectStore)
            {
            }

            public function store(Sale $sale): void
            {
                if (! $this->expectStore) {
                    TestCase::fail('Repository store should not be called.');
                }

                $this->stored = true;
            }

            public function findById(SaleId $id): ?Sale
            {
                return $this->sale;
            }

            public function getById(SaleId $id): Sale
            {
                return $this->sale;
            }
        };
    }
}
