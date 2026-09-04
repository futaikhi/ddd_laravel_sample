<?php

declare(strict_types=1);

namespace Tests\Unit\Sales\Handlers;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Src\Sales\Application\Commands\Create\CreateSaleCommand;
use Src\Sales\Application\Commands\Create\CreateSaleHandler;
use Src\Sales\Application\Commands\Create\CreateSaleLineItem;
use Src\Sales\Domain\Entities\Sale;
use Src\Sales\Domain\Enums\OrderStatus;
use Src\Sales\Domain\Exceptions\CustomerNotFoundException;
use Src\Sales\Domain\Ports\CustomerExistenceCheckerInterface;
use Src\Sales\Domain\Ports\ProductCatalogInterface;
use Src\Sales\Domain\Repositories\SaleRepositoryInterface;
use Src\Sales\Domain\ValueObjects\CustomerId;
use Src\Sales\Domain\ValueObjects\LineItem;
use Src\Sales\Domain\ValueObjects\Money;
use Src\Sales\Domain\ValueObjects\ProductId;
use Src\Sales\Domain\ValueObjects\SaleId;
use Src\Sales\Domain\ValueObjects\SalesFilter;

final class CreateSaleHandlerTest extends TestCase
{
    public function test_it_returns_void_and_does_not_leak_read_data(): void
    {
        $repo = $this->makeRepo();
        $checker = $this->makeChecker(true);
        $catalog = $this->makeCatalog(Money::fromCents(30000, 'IDR'));
        $handler = new CreateSaleHandler($repo, $checker, $catalog);

        $command = new CreateSaleCommand(
            id: SaleId::random(),
            customerId: CustomerId::random(),
            items: [new CreateSaleLineItem('01H8M6KJ5NQ8XX4P0N2VYJ4K5D', 2)],
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
        $handler = new CreateSaleHandler($repo, $this->makeChecker(true), $this->makeCatalog(Money::fromCents(50000, 'IDR')));
        $saleId = SaleId::random();

        $handler(new CreateSaleCommand(
            id: $saleId,
            customerId: CustomerId::random(),
            items: [new CreateSaleLineItem('01H8M6KJ5NQ8XX4P0N2VYJ4K5D', 1)],
        ));

        $this->assertNotNull($repo->stored, 'Handler must persist sale through write repository');
        $this->assertSame($saleId->getValue(), $repo->stored->getId()->getValue());
        $this->assertSame(OrderStatus::PENDING, $repo->stored->getStatus());
        $this->assertNotEmpty($repo->stored->pullDomainEvents(), 'Handler must pass aggregate recorded events to repository');
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

        $this->assertSame([SaleRepositoryInterface::class, CustomerExistenceCheckerInterface::class, ProductCatalogInterface::class], $paramTypes);
        foreach ($paramTypes as $paramType) {
            $this->assertStringNotContainsString('ReadModelRepository', $paramType, 'Command handler must not depend on read-model repositories');
        }
    }

    public function test_it_rejects_unknown_customer_before_resolving_catalog_or_persisting(): void
    {
        $repo = $this->makeRepo();
        $catalog = $this->createMock(ProductCatalogInterface::class);
        $catalog->expects($this->never())->method('lineItemFor');
        $handler = new CreateSaleHandler($repo, $this->makeChecker(false), $catalog);

        $this->expectException(CustomerNotFoundException::class);

        try {
            $handler(new CreateSaleCommand(
                id: SaleId::random(),
                customerId: CustomerId::random(),
                items: [new CreateSaleLineItem('01H8M6KJ5NQ8XX4P0N2VYJ4K5D', 1)],
            ));
        } finally {
            $this->assertNull($repo->stored, 'Handler must NOT persist sale when customer does not exist');
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

    private function makeChecker(bool $result): CustomerExistenceCheckerInterface
    {
        return new class($result) implements CustomerExistenceCheckerInterface {
            public function __construct(private bool $result)
            {
            }

            public function exists(CustomerId $customerId): bool
            {
                return $this->result;
            }
        };
    }

    private function makeCatalog(Money $unitPrice): ProductCatalogInterface
    {
        return new class($unitPrice) implements ProductCatalogInterface {
            public function __construct(private Money $unitPrice)
            {
            }

            public function lineItemFor(string $productId, int $quantity): LineItem
            {
                return new LineItem(
                    productId: ProductId::fromString($productId),
                    quantity: $quantity,
                    unitPrice: $this->unitPrice,
                );
            }
        };
    }
}
