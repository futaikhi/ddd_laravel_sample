<?php

declare(strict_types=1);

namespace Src\Sales\Domain\Exceptions;

use RuntimeException;
use Src\Shared\Framework\Domain\Exceptions\VisibleExceptionInterface;

final class SaleCannotBeCompletedException extends RuntimeException implements VisibleExceptionInterface
{
    public static function notConfirmed(string $currentStatus): self
    {
        return new self("Sale cannot be completed. Current status is '{$currentStatus}'. Only confirmed sales can be completed.");
    }
}
