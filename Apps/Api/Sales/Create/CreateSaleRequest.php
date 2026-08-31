<?php

declare(strict_types=1);

namespace Apps\Api\Sales\Create;

use Apps\Shared\Http\AbstractFormRequest;
use Src\Sales\Domain\ValueObjects\CustomerId;
use Src\Sales\Domain\ValueObjects\LineItem;
use Src\Sales\Domain\ValueObjects\Money;
use Src\Sales\Domain\ValueObjects\SaleId;

final class CreateSaleRequest extends AbstractFormRequest
{
    public function getDto(): CreateSaleDto
    {
        $itemsInput = $this->getHelper()->getArrayOrNull('line_items') ?? [];
        $lineItems = [];

        foreach ($itemsInput as $item) {
            $lineItems[] = new LineItemInputDto(
                productId: (string) ($item['product_id'] ?? ''),
                quantity: (int) ($item['quantity'] ?? 0),
            );
        }

        return new CreateSaleDto(
            id: SaleId::random(),
            customerId: CustomerId::fromString($this->getHelper()->getString('customer_id')),
            lineItems: $lineItems,
        );
    }
}
