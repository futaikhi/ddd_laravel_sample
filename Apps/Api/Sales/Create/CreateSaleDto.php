<?php

declare(strict_types=1);

namespace Apps\Api\Sales\Create;

use Src\Sales\Domain\ValueObjects\CustomerId;
use Src\Sales\Domain\ValueObjects\SaleId;

final readonly class CreateSaleDto
{
    /**
     * @param array<LineItemInputDto> $lineItems
     */
    public function __construct(
        public SaleId $id,
        public CustomerId $customerId,
        public array $lineItems,
    ) {
    }
}
