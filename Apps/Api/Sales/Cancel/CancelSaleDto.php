<?php

declare(strict_types=1);

namespace Apps\Api\Sales\Cancel;

use Src\Sales\Domain\ValueObjects\SaleId;

final readonly class CancelSaleDto
{
    public function __construct(
        public SaleId $saleId,
        public string $reason,
    ) {
    }
}
