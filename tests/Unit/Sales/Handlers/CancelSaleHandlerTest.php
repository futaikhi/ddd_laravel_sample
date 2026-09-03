<?php

declare(strict_types=1);

namespace Tests\Unit\Sales\Handlers;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Src\Sales\Application\Commands\Cancel\CancelSaleCommand;
use Src\Sales\Application\Commands\Cancel\CancelSaleHandler;
use Src\Sales\Domain\Entities\Sale;
use Src\Sales\Domain\Enums\OrderStatus;
use Src\Sales\Domain\Enums\PaymentMethod;
use Src\Sales\Domain\Events\SaleCancelledEvent;
use Src\Sales\Domain\Ports\PaymentGatewayInterface;
use Src\Sales\Domain\Repositories\SaleRepositoryInterface;
use Src\Sales\Domain\ValueObjects\CustomerId;
use Src\Sales\Domain\ValueObjects\LineItem;
use Src\Sales\Domain\ValueObjects\Money;
use Src\Sales\Domain\ValueObjects\PaymentRequest;
use Src\Sales\Domain\ValueObjects\PaymentResult;
use Src\Sales\Domain\ValueObjects\ProductId;
use Src\Sales\Domain\ValueObjects\SaleId;
use Src\Sales\Domain\ValueObjects\SalesFilter;

/**
 * AC-004 command-side contract {@see CancelSaleHandler}.
 *
 * Proves handler:
 * - returns void (no read data leaks back through the command bus),
 * - persists aggregate through SaleRepositoryInterface (write side only),
 * - leaves SaleCancelledEvent recorded so the repository persistence boundary can publish it,
 * - refunds confirmed sales before cancelling,
 * - does not depend on read-model repositories or the event bus directly.
 */
final class CancelSaleHandlerTest extends TestCase
{
    public function test_it_returns_void_and_does_not_leak_read_data(): void
    {
        $sale = $this->makePendingSale();
        $repo = $this->makeRepo($sale);
        $paymentGateway = $this->createMock(PaymentGatewayInterface::class);

        $handler = new CancelSaleHandler($repo, $paymentGateway);

        $result = $handler(new CancelSaleCommand(
            id: $sale->getId(),
            reason: 'customer changed mind',
        ));

        $this->assertNull($result, 'Command handler must return void; must not leak read data');

        $returnType = (new ReflectionMethod(CancelSaleHandler::class, '__invoke'))->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame('void', (string) $returnType);
    }

    public function test_it_persists_pending_sale_with_cancelled_event_for_repository_publishing(): void
    {
        $sale = $this->makePendingSale();
        $repo = $this->makeRepo($sale);

        $paymentGateway = $this->createMock(PaymentGatewayInterface::class);
        $paymentGateway->expects($this->never())->method('refund');
        $paymentGateway->expects($this->never())->method('process');

        $handler = new CancelSaleHandler($repo, $paymentGateway);
        $handler(new CancelSaleCommand(
            id: $sale->getId(),
            reason: 'customer changed mind',
        ));

        $this->assertTrue($repo->stored, 'Handler must persist sale through the write repository');
        $this->assertSame(OrderStatus::CANCELLED, $sale->getStatus());
        $this->assertSame('customer changed mind', $sale->getCancellationReason());
        $this->assertNotNull($sale->getCancelledAt());

        $events = $sale->pullDomainEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(SaleCancelledEvent::class, $events[0]);
        $this->assertSame($sale->getId()->getValue(), $events[0]->saleId);
        $this->assertSame('customer changed mind', $events[0]->reason);
        $this->assertNotEmpty($events[0]->cancelledAt);
    }

    public function test_it_refunds_confirmed_sale_and_records_cancelled_event_for_repository_publishing(): void
    {
        $sale = $this->makeConfirmedSale();
        $repo = $this->makeRepo($sale);

        $paymentGateway = $this->createMock(PaymentGatewayInterface::class);
        $paymentGateway->expects($this->once())
            ->method('refund')
            ->with('TXN-TEST-001');

        $handler = new CancelSaleHandler($repo, $paymentGateway);
        $handler(new CancelSaleCommand(
            id: $sale->getId(),
            reason: 'fraud detected',
        ));

        $this->assertTrue($repo->stored);
        $this->assertSame(OrderStatus::CANCELLED, $sale->getStatus());
        $this->assertSame('fraud detected', $sale->getCancellationReason());

        $events = $sale->pullDomainEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(SaleCancelledEvent::class, $events[0]);
        $this->assertSame($sale->getId()->getValue(), $events[0]->saleId);
        $this->assertSame('fraud detected', $events[0]->reason);
        $this->assertNotEmpty($events[0]->cancelledAt);
    }

    public function test_it_only_depends_on_write_side_ports(): void
    {
        $constructor = (new \ReflectionClass(CancelSaleHandler::class))->getConstructor();
        $this->assertNotNull($constructor);

        $paramTypes = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof \ReflectionNamedType) {
                $paramTypes[] = $type->getName();
            }
        }

        $this->assertContains(SaleRepositoryInterface::class, $paramTypes);
        $this->assertContains(PaymentGatewayInterface::class, $paramTypes);

        foreach ($paramTypes as $paramType) {
            $this->assertStringNotContainsString(
                'ReadModelRepository',
                $paramType,
                'Command handler must not depend on read-model repositories'
            );
            $this->assertStringNotContainsString(
                'EventBus',
                $paramType,
                'Repository is responsible for publishing recorded domain events'
            );
        }
    }

    private function makePendingSale(): Sale
    {
        $sale = Sale::create(
            SaleId::random(),
            CustomerId::random(),
            [new LineItem(
                productId: ProductId::fromString('01H8M6KJ5NQ8XX4P0N2VYJ4K5D'),
                quantity: 2,
                unitPrice: Money::fromCents(50000, 'IDR')
            )],
        );

        // Drain SaleCreated event so assertions only observe cancel events.
        $sale->releaseEvents();

        return $sale;
    }

    private function makeConfirmedSale(): Sale
    {
        $sale = Sale::create(
            SaleId::random(),
            CustomerId::random(),
            [new LineItem(
                productId: ProductId::fromString('01H8M6KJ5NQ8XX4P0N2VYJ4K5D'),
                quantity: 2,
                unitPrice: Money::fromCents(50000, 'IDR')
            )],
        );

        $sale->confirm(PaymentMethod::CREDIT_CARD, 'TXN-TEST-001');
        $sale->releaseEvents();

        return $sale;
    }

    private function makeRepo(Sale $sale): object
    {
        return new class($sale) implements SaleRepositoryInterface {
            public bool $stored = false;

            public function __construct(private Sale $sale)
            {
            }

            public function store(Sale $sale): void
            {
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

            /** @return list<Sale> */
            public function list(SalesFilter $filter): array
            {
                return [$this->sale];
            }
        };
    }
}
