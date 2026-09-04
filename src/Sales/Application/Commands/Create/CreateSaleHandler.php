<?php

declare(strict_types=1);

namespace Src\Sales\Application\Commands\Create;

use InvalidArgumentException;
use Src\Sales\Domain\Entities\Sale;
use Src\Sales\Domain\Exceptions\CustomerNotFoundException;
use Src\Sales\Domain\Ports\CustomerExistenceCheckerInterface;
use Src\Sales\Domain\Ports\ProductCatalogInterface;
use Src\Sales\Domain\Repositories\SaleRepositoryInterface;
use Src\Sales\Domain\ValueObjects\LineItem;
use Src\Shared\Framework\Infrastructure\Bus\CommandBus\CommandHandlerInterface;

final readonly class CreateSaleHandler implements CommandHandlerInterface
{
    public function __construct(
        private SaleRepositoryInterface $repository,
        private CustomerExistenceCheckerInterface $customers,
        private ProductCatalogInterface $products,
    ) {
    }

    public function __invoke(CreateSaleCommand $command): void
    {
        if (! $this->customers->exists($command->customerId)) {
            throw CustomerNotFoundException::withId($command->customerId);
        }

        /** @var list<LineItem> $lineItems */
        $lineItems = [];

        foreach ($command->items as $item) {
            if (! $item instanceof CreateSaleLineItem) {
                throw new InvalidArgumentException('Create sale items must be CreateSaleLineItem instances.');
            }

            $lineItems[] = $this->products->lineItemFor($item->productId, $item->quantity);
        }

        $sale = Sale::create(
            id: $command->id,
            customerId: $command->customerId,
            lineItems: $lineItems,
            agentId: $command->agentId,
        );

        $this->repository->store($sale);
    }
}
