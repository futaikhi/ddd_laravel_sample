<?php

declare(strict_types=1);

namespace Apps\Api\Sales\Create;

use Apps\Api\Sales\Shared\SaleCreatedRes;
use Src\Sales\Application\Commands\Create\CreateSaleCommand;
use Src\Sales\Application\Commands\Create\CreateSaleLineItem;
use Src\Sales\Domain\Ports\InvoiceNumberGeneratorInterface;
use Src\Shared\Framework\Infrastructure\Bus\CommandBus\CommandBusInterface;

final readonly class CreateSaleAction
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private InvoiceNumberGeneratorInterface $invoiceNumbers,
    ) {
    }

    public function __invoke(CreateSaleDto $dto): SaleCreatedRes
    {
        $items = array_map(
            static fn (LineItemInputDto $item): CreateSaleLineItem => new CreateSaleLineItem(
                productId: $item->productId,
                quantity: $item->quantity,
            ),
            $dto->lineItems,
        );

        $invoiceNumber = $this->invoiceNumbers->next();

        $this->commandBus->dispatch(new CreateSaleCommand(
            id: $dto->id,
            customerId: $dto->customerId,
            items: $items,
            invoiceNumber: $invoiceNumber,
        ));

        return new SaleCreatedRes(
            id: $dto->id->getValue(),
            invoiceNumber: $invoiceNumber->getValue(),
        );
    }
}
