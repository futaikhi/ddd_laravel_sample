<?php

declare(strict_types=1);

namespace Src\Sales\Domain\Exceptions;

use RuntimeException;

final class ProductNotFoundException extends RuntimeException
{
    public static function withId(string $productId): self
    {
        return new self(sprintf('Product %s was not found.', $productId));
    }
}
