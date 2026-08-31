<?php

declare(strict_types=1);

namespace Apps\Api\Sales\Cancel;

use Apps\Api\Sales\Shared\SaleActionRes;
use Src\Sales\Application\Commands\Cancel\CancelSaleCommand;
use Src\Shared\Framework\Infrastructure\Bus\CommandBus\CommandBusInterface;

final readonly class CancelSaleAction
{
    public function __construct(
        private CommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(CancelSaleDto $dto): SaleActionRes
    {
        $this->commandBus->dispatch(new CancelSaleCommand(
            id: $dto->saleId,
            reason: $dto->reason,
        ));

        return new SaleActionRes(message: 'Sale cancelled successfully');
    }
}
