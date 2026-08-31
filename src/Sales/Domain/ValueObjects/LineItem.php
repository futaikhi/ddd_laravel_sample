<?php

declare(strict_types=1);

namespace Src\Sales\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class LineItem
{
    public function __construct(
        public ProductId $productId,
        public int $quantity,
        public Money $unitPrice,
    ) {
        if ($this->productId->getValue() === '') {
            throw new InvalidArgumentException('Product ID cannot be empty');
        }

        if ($this->quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero');
        }

        if ($this->unitPrice->amount <= 0) {
            throw new InvalidArgumentException('Unit price must be greater than zero');
        }
    }

    public function getTotal(): Money
    {
        return $this->unitPrice->multiply($this->quantity);
    }
}
