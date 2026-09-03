<?php

declare(strict_types=1);

namespace Src\Sales\Application\Commands\Complete;

use Src\Sales\Domain\Ports\CommissionCalculatorInterface;
use Src\Sales\Domain\Repositories\SaleRepositoryInterface;
use Src\Shared\Framework\Infrastructure\Bus\CommandBus\CommandHandlerInterface;

final readonly class CompleteSaleHandler implements CommandHandlerInterface
{
    public function __construct(
        private SaleRepositoryInterface $repository,
        private CommissionCalculatorInterface $commissionCalculator,
    ) {
    }

    /**
     * Orchestrates:
     * 1. Load confirmed sale
     * 2. Ask commission service (hexagonal port) how much commission owed
     * 3. Transition aggregate COMPLETED (aggregate locks the commission)
     * 4. Persist; repository publishes recorded domain events
     */
    public function __invoke(CompleteSaleCommand $command): void
    {
        $sale = $this->repository->getById($command->id);

        $commission = $this->commissionCalculator->calculate($sale);

        $sale->complete($commission);

        $this->repository->store($sale);
    }
}
