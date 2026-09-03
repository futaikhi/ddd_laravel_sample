<?php

declare(strict_types=1);

namespace Tests\Unit\Sales\Handlers;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Src\Sales\Application\Commands\Complete\CompleteSaleCommand;
use Src\Sales\Application\Commands\Complete\CompleteSaleHandler;
use Src\Sales\Domain\Entities\Sale;
use Src\Sales\Domain\Enums\OrderStatus;
use Src\Sales\Domain\Enums\PaymentMethod;
use Src\Sales\Domain\Events\SaleCompletedEvent;
use Src\Sales\Domain\Ports\CommissionCalculatorInterface;
use Src\Sales\Domain\Repositories\SaleRepositoryInterface;
use Src\Sales\Domain\ValueObjects\Commission;
use Src\Sales\Domain\ValueObjects\CustomerId;
use Src\Sales\Domain\ValueObjects\LineItem;
use Src\Sales\Domain\ValueObjects\Money;
use Src\Sales\Domain\ValueObjects\ProductId;
use Src\Sales\Domain\ValueObjects\SaleId;
use Src\Sales\Domain\ValueObjects\SalesFilter;

/**
 * AC-004 command-side contract {@see CompleteSaleHandler}.
 *
 * Proves handler:
 * - returns void (no read data leaks back through the command bus),
 * - persists aggregate through SaleRepositoryInterface (write side only),
 * - leaves SaleCompletedEvent recorded so the repository persistence boundary can publish it,
 * - does not depend on read-model repositories or the event bus directly.
 */
final class CompleteSaleHandlerTest extends TestCase
{
    public function test_it_returns_void_and_does_not_leak_read_data(): void
    {
        $sale = $this->makeConfirmedSale();
        $repo = $this->makeRepo($sale);
        $commissionCalculator = $this->makeCommissionCalculator();

        $handler = new CompleteSaleHandler($repo, $commissionCalculator);

        $result = $handler(new CompleteSaleCommand(id: $sale->getId()));

        $this->assertNull($result, 'Command handler must return void; must not leak read data');

        $returnType = (new ReflectionMethod(CompleteSaleHandler::class, '__invoke'))->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame('void', (string) $returnType);
    }

    public function test_it_persists_the_completed_aggregate_with_recorded_event_for_repository_publishing(): void
    {
        $sale = $this->makeConfirmedSale();
        $repo = $this->makeRepo($sale);
        $commissionCalculator = $this->makeCommissionCalculator();

        $handler = new CompleteSaleHandler($repo, $commissionCalculator);
        $handler(new CompleteSaleCommand(id: $sale->getId()));

        $this->assertTrue($repo->stored, 'Handler must persist sale through the write repository');
        $this->assertSame(OrderStatus::COMPLETED, $sale->getStatus());
        $this->assertNotNull($sale->getCommission());

        $events = $sale->pullDomainEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(SaleCompletedEvent::class, $events[0]);
        $this->assertSame($sale->getId()->getValue(), $events[0]->saleId);
        $this->assertSame(100000, $events[0]->totalAmount);
        $this->assertSame(3000, $events[0]->commissionAmount);
        $this->assertEqualsWithDelta(3.0, $events[0]->commissionRate, 0.001);
        $this->assertSame('IDR', $events[0]->commissionCurrency);
        $this->assertNotEmpty($events[0]->completedAt);
    }

    public function test_it_only_depends_on_write_side_ports(): void
    {
        $constructor = (new \ReflectionClass(CompleteSaleHandler::class))->getConstructor();
        $this->assertNotNull($constructor);

        $paramTypes = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof \ReflectionNamedType) {
                $paramTypes[] = $type->getName();
            }
        }

        $this->assertContains(SaleRepositoryInterface::class, $paramTypes);
        $this->assertContains(CommissionCalculatorInterface::class, $paramTypes);

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
        // Drain events collected during create/confirm so assertions only observe complete().
        $sale->releaseEvents();

        return $sale;
    }

    private function makeCommissionCalculator(): CommissionCalculatorInterface
    {
        return new class implements CommissionCalculatorInterface {
            public function calculate(Sale $sale): Commission
            {
                return Commission::fromRate($sale->getTotalAmount(), 3.0, 'test');
            }
        };
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
