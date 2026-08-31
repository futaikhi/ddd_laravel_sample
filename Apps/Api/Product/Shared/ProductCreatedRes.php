<?php

declare(strict_types=1);

namespace Apps\Api\Product\Shared;

use Apps\Shared\Http\BaseRes;

final readonly class ProductCreatedRes extends BaseRes
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
