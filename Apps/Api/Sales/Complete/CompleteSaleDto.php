<?php

declare(strict_types=1);

namespace Apps\Api\Sales\Complete;

use Src\Sales\Domain\ValueObjects\SaleId;

final readonly class CompleteSaleDto
{
    public function __construct(
        public SaleId $saleId,
    ) {
    }
}
