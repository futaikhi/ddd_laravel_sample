<?php

declare(strict_types=1);

namespace Apps\Api\Product\Create;

final readonly class CreateProductDto
{
    public function __construct(
        public string $id,
        public string $name,
        public string $sku,
        public int $price,
        public string $currency,
    ) {
    }
}
