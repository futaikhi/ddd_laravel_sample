<?php

declare(strict_types=1);

namespace Apps\Api\Sales\Create;

final readonly class LineItemInputDto
{
    public function __construct(
        public string $productId,
        public int $quantity,
    ) {
    }
}
