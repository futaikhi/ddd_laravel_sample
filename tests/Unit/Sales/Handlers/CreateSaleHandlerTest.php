<?php

declare(strict_types=1);

namespace Tests\Unit\Sales\Handlers;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Src\Sales\Application\Commands\Create\CreateSaleCommand;
use Src\Sales\Application\Commands\Create\CreateSaleHandler;
use Src\Sales\Domain\Entities\Sale;
use Src\Sales\Domain\Enums\OrderStatus;
use Src\Sales\Domain\Repositories\SaleRepositoryInterface;
use Src\Sales\Domain\ValueObjects\CustomerId;
use Src\Sales\Domain\ValueObjects\LineItem;
use Src\Sales\Domain\ValueObjects\Money;
use Src\Sales\Domain\ValueObjects\ProductId;
use Src\Sales\Domain\ValueObjects\SaleId;
use Src\Sales\Domain\ValueObjects\SalesFilter;

/**
 * AC-003 command-side contract {@see CreateSaleHandler}.
 *
 * Proves handler:
 * - Returns void (no read data leaks back through the command bus).
 * - Persists aggregate through SaleRepositoryInterface (write side only).
 * - Leaves domain-event publishing to the repository persistence boundary.
 * - Does not depend on read-model repositories.
 */
final class CreateSaleHandlerTest extends TestCase
{
    public function test_it_returns_void_and_does_not_leak_read_data(): void
    {
        $repo = $this->makeRepo();
        $handler = new CreateSaleHandler($repo);

        $command = new CreateSaleCommand(
            id: SaleId::random(),
            customerId: CustomerId::random(),
            lineItems: [
                new LineItem(
                    productId: ProductId::fromString('01H8M6KJ5NQ8XX4P0N2VYJ4K5D'),
                    quantity: 2,
                    unitPrice: Money::fromCents(30000, 'IDR'),
                ),
            ],
        );

        $result = $handler($command);

        $this->assertNull($result, 'Command handler must return void; must not leak read data');

        $returnType = (new ReflectionMethod(CreateSaleHandler::class, '__invoke'))->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame('void', (string) $returnType);
    }

    public function test_it_persists_the_created_aggregate_with_recorded_events_for_repository_publishing(): void
    {
        $repo = $this->makeRepo();
        $handler = new CreateSaleHandler($repo);
        $saleId = SaleId::random();

        $handler(new CreateSaleCommand(
            id: $saleId,
            customerId: CustomerId::random(),
            lineItems: [
                new LineItem(
                    productId: ProductId::fromString('01H8M6KJ5NQ8XX4P0N2VYJ4K5D'),
                    quantity: 1,
                    unitPrice: Money::fromCents(50000, 'IDR'),
                ),
            ],
        ));

        $this->assertNotNull($repo->stored, 'Handler must persist sale through write repository');
        $this->assertSame($saleId->getValue(), $repo->stored->getId()->getValue());
        $this->assertSame(OrderStatus::PENDING, $repo->stored->getStatus());
        $this->assertNotEmpty(
            $repo->stored->pullDomainEvents(),
            'Handler must pass aggregate with recorded events to repository so repository can publish after persistence',
        );
    }

    public function test_it_only_depends_on_write_side_ports(): void
    {
        $constructor = (new \ReflectionClass(CreateSaleHandler::class))->getConstructor();
        $this->assertNotNull($constructor);

        $paramTypes = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof \ReflectionNamedType) {
                $paramTypes[] = $type->getName();
            }
        }

        $this->assertSame([SaleRepositoryInterface::class], $paramTypes);

        foreach ($paramTypes as $paramType) {
            $this->assertStringNotContainsString(
                'ReadModelRepository',
                $paramType,
                'Command handler must not depend on read-model repositories',
            );
        }
    }

    private function makeRepo(): object
    {
        return new class implements SaleRepositoryInterface {
            public ?Sale $stored = null;

            public function store(Sale $sale): void
            {
                $this->stored = $sale;
            }

            public function findById(SaleId $id): ?Sale
            {
                return $this->stored;
            }

            public function getById(SaleId $id): Sale
            {
                if ($this->stored === null) {
                    throw new \RuntimeException('sale not found');
                }

                return $this->stored;
            }

            /** @return list<Sale> */
            public function list(SalesFilter $filter): array
            {
                return $this->stored === null ? [] : [$this->stored];
            }
        };
    }
}
