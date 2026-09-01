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
            if (!is_array($item)) {
                continue;
            }

            $productId = $item['product_id'] ?? '';
            $quantity  = $item['quantity']   ?? 0;

            $lineItems[] = new LineItemInputDto(
                productId: is_scalar($productId) ? (string) $productId : '',
                quantity: is_numeric($quantity) ? (int) $quantity : 0,
            );
        }

        return new CreateSaleDto(
            id: SaleId::random(),
            customerId: CustomerId::fromString($this->getHelper()->getString('customer_id')),
            lineItems: $lineItems,
        );
    }
}
