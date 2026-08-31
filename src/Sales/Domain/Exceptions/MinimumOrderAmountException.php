<?php

declare(strict_types=1);

namespace Src\Sales\Domain\Exceptions;

use RuntimeException;
use Src\Shared\Framework\Domain\Exceptions\VisibleExceptionInterface;

final class MinimumOrderAmountException extends RuntimeException implements VisibleExceptionInterface
{
    public static function belowMinimum(int $total, int $minimum): self
    {
        return new self(sprintf(
            'The sale total is %d, but the minimum order amount is %d.',
            $total,
            $minimum,
        ));
    }
}
