<?php

declare(strict_types=1);

namespace Src\Sales\Application\Commands\Create;

use Src\Sales\Domain\Entities\Sale;
use Src\Sales\Domain\Repositories\SaleRepositoryInterface;
use Src\Shared\Framework\Infrastructure\Bus\CommandBus\CommandHandlerInterface;
use Src\Shared\Framework\Infrastructure\Bus\EventBus\EventBusInterface;

final readonly class CreateSaleHandler implements CommandHandlerInterface
{
    public function __construct(
        private SaleRepositoryInterface $repository,
        private EventBusInterface $eventBus,
    ) {
    }

    public function __invoke(CreateSaleCommand $command): void
    {
        $sale = Sale::create(
            id: $command->id,
            customerId: $command->customerId,
            lineItems: $command->lineItems,
        );

        $this->repository->store($sale);
        $this->eventBus->publishEvents($sale->releaseEvents());
    }
}
