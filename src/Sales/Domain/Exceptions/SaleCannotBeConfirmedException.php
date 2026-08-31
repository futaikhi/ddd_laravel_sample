<?php

declare(strict_types=1);

namespace Src\Sales\Domain\Exceptions;

use RuntimeException;
use Src\Shared\Framework\Domain\Exceptions\VisibleExceptionInterface;

final class SaleCannotBeConfirmedException extends RuntimeException implements VisibleExceptionInterface
{
    public static function notPending(string $currentStatus): self
    {
        return new self("Sale cannot be confirmed. Current status is '{$currentStatus}'. Only pending sales can be confirmed.");
    }
}
