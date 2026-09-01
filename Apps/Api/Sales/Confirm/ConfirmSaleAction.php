<?php

declare(strict_types=1);

namespace Apps\Api\Sales\Confirm;

use Apps\Api\Sales\Shared\SaleActionRes;
use Src\Sales\Application\Commands\Confirm\ConfirmSaleCommand;
use Src\Shared\Framework\Infrastructure\Bus\CommandBus\CommandBusInterface;

final readonly class ConfirmSaleAction
{
    public function __construct(
        private CommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(ConfirmSaleDto $dto): SaleActionRes
    {
        $this->commandBus->dispatch(new ConfirmSaleCommand(
            id: $dto->saleId,
            paymentMethod: $dto->paymentMethod,
        ));

        return new SaleActionRes(message: 'Sale confirmed successfully');
    }
}
