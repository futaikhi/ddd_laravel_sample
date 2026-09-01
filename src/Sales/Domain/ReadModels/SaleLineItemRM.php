<?php

declare(strict_types=1);

namespace Src\Sales\Domain\ReadModels;

final readonly class SaleLineItemRM
{
    public function __construct(
        public string $productId,
        public int $quantity,
        public int $unitPrice,
        public string $currency,
        public int $lineTotal,
    ) {
    }
}
