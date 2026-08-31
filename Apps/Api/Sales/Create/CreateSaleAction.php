<?php

declare(strict_types=1);

namespace Apps\Api\Sales\Create;

use Apps\Api\Sales\Shared\SaleCreatedRes;
use Src\Sales\Application\Commands\Create\CreateSaleCommand;
use Src\Sales\Domain\ValueObjects\LineItem;
use Src\Sales\Domain\ValueObjects\Money;
use Src\Shared\Framework\Infrastructure\Bus\CommandBus\CommandBusInterface;

final readonly class CreateSaleAction
{
    public function __construct(
        private CommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(CreateSaleDto $dto): SaleCreatedRes
    {
        $lineItems = array_map(
            static fn (LineItemInputDto $item): LineItem => new LineItem(
                productId: $item->productId,
                quantity: $item->quantity,
                unitPrice: new Money($item->unitPrice, $item->currency),
            ),
            $dto->lineItems,
        );

        $this->commandBus->dispatch(new CreateSaleCommand(
            id: $dto->id,
            customerId: $dto->customerId,
            lineItems: $lineItems,
        ));

        return new SaleCreatedRes(id: $dto->id->getValue());
    }
}
