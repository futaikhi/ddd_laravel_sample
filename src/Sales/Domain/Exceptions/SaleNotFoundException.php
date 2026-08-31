<?php

declare(strict_types=1);

namespace Src\Sales\Domain\Exceptions;

use RuntimeException;
use Src\Sales\Domain\ValueObjects\SaleId;
use Src\Shared\Framework\Domain\Exceptions\VisibleExceptionInterface;

final class SaleNotFoundException extends RuntimeException implements VisibleExceptionInterface
{
    public static function withId(SaleId $id): self
    {
        return new self(sprintf('Sale with id %s was not found.', $id->getValue()));
    }
}
