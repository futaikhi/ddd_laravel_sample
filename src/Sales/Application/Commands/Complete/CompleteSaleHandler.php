<?php

declare(strict_types=1);

namespace Src\Sales\Application\Commands\Complete;

use Src\Sales\Domain\Repositories\SaleRepositoryInterface;
use Src\Shared\Framework\Infrastructure\Bus\CommandBus\CommandHandlerInterface;
use Src\Shared\Framework\Infrastructure\Bus\EventBus\EventBusInterface;

final readonly class CompleteSaleHandler implements CommandHandlerInterface
{
    public function __construct(
        private SaleRepositoryInterface $repository,
        private EventBusInterface $eventBus,
    ) {
    }

    public function __invoke(CompleteSaleCommand $command): void
    {
        $sale = $this->repository->getById($command->id);
        $sale->complete();

        $this->repository->store($sale);
        $this->eventBus->publishEvents($sale->releaseEvents());
    }
}
