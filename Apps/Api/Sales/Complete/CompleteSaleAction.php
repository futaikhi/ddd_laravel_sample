<?php

declare(strict_types=1);

namespace Apps\Api\Sales\Complete;

use Apps\Api\Sales\Shared\SaleActionRes;
use Src\Sales\Application\Commands\Complete\CompleteSaleCommand;
use Src\Shared\Framework\Infrastructure\Bus\CommandBus\CommandBusInterface;

final readonly class CompleteSaleAction
{
    public function __construct(
        private CommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(CompleteSaleDto $dto): SaleActionRes
    {
        $this->commandBus->dispatch(new CompleteSaleCommand(
            id: $dto->saleId,
        ));

        return new SaleActionRes(message: 'Sale completed successfully');
    }
}
