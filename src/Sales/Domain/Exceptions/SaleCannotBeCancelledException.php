<?php

declare(strict_types=1);

namespace Src\Sales\Domain\Exceptions;

use RuntimeException;
use Src\Shared\Framework\Domain\Exceptions\VisibleExceptionInterface;

final class SaleCannotBeCancelledException extends RuntimeException implements VisibleExceptionInterface
{
    public static function invalidStatus(string $currentStatus): self
    {
        return new self("Sale cannot be cancelled. Current status is '{$currentStatus}'. Only pending or confirmed sales can be cancelled.");
    }

    public static function alreadyCancelled(): self
    {
        return new self('Sale is already cancelled.');
    }
}
