<?php

declare(strict_types=1);

namespace Src\Sales\Domain\Exceptions;

use RuntimeException;
use Src\Shared\Framework\Domain\Exceptions\VisibleExceptionInterface;

final class SaleCannotBeCancelledException extends RuntimeException implements VisibleExceptionInterface
{
    public static function invalidStatus(string $currentStatus): self
    {
        return new self("Sale with status {$currentStatus} cannot be cancelled.");
    }

    public static function alreadyCancelled(): self
    {
        return new self('Sale is already cancelled.');
    }

    public static function missingTransactionId(): self
    {
        return new self('Confirmed sale cannot be cancelled because payment transaction id is missing.');
    }
}
