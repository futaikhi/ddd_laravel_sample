<?php

declare(strict_types=1);

namespace Apps\Api\Sales\Create;

use Apps\Api\Sales\Shared\SaleCreatedRes;
use Src\Sales\Application\Commands\Create\CreateSaleCommand;
use Src\Sales\Domain\Exceptions\CustomerNotFoundException;
use Src\Sales\Domain\Exceptions\ProductNotFoundException;
use Src\Sales\Domain\ValueObjects\LineItem;
use Src\Sales\Domain\ValueObjects\Money;
use Src\Sales\Domain\ValueObjects\ProductId;
use Src\Shared\Framework\Infrastructure\Bus\CommandBus\CommandBusInterface;

final readonly class CreateSaleAction
{
    public function __construct(
        private CommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(CreateSaleDto $dto): SaleCreatedRes
    {
        $customer = \App\Models\Customer::query()->find($dto->customerId->getValue());
        if ($customer === null) {
            throw CustomerNotFoundException::withId($dto->customerId);
        }

        /** @var list<LineItem> $lineItems */
        $lineItems = [];
        foreach ($dto->lineItems as $item) {
            $product = \App\Models\Product::query()->find($item->productId);
            if ($product === null) {
                throw ProductNotFoundException::withId($item->productId);
            }

            $lineItems[] = new LineItem(
                productId: ProductId::fromString($item->productId),
                quantity: $item->quantity,
                unitPrice: new Money((int) $product->price, (string) $product->currency),
            );
        }

        $this->commandBus->dispatch(new CreateSaleCommand(
            id: $dto->id,
            customerId: $dto->customerId,
            lineItems: $lineItems,
        ));

        return new SaleCreatedRes(id: $dto->id->getValue());
    }
}
