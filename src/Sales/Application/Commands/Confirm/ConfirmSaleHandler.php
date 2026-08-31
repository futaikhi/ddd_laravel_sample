<?php

declare(strict_types=1);

namespace Src\Sales\Application\Commands\Confirm;

use Src\Sales\Domain\Repositories\SaleRepositoryInterface;
use Src\Shared\Framework\Infrastructure\Bus\CommandBus\CommandHandlerInterface;
use Src\Shared\Framework\Infrastructure\Bus\EventBus\EventBusInterface;

final readonly class ConfirmSaleHandler implements CommandHandlerInterface
{
    public function __construct(
        private SaleRepositoryInterface $repository,
        private EventBusInterface $eventBus,
    ) {
    }

    public function __invoke(ConfirmSaleCommand $command): void
    {
        $sale = $this->repository->getById($command->id);
        $sale->confirm();

        $this->repository->store($sale);
        $this->eventBus->publishEvents($sale->releaseEvents());
    }
}
