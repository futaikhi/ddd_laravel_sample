<?php

declare(strict_types=1);

namespace Apps\Api\Sales\Confirm;

use Src\Sales\Domain\ValueObjects\SaleId;

final readonly class ConfirmSaleDto
{
    public function __construct(
        public SaleId $saleId,
    ) {
    }
}
