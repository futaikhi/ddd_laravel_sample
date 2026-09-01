<?php

declare(strict_types=1);

namespace Apps\Api\Sales\Show;

use Src\Sales\Domain\ValueObjects\SaleId;

final readonly class ShowSaleDto
{
    public function __construct(
        public SaleId $saleId,
    ) {
    }
}
