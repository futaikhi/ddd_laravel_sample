<?php

declare(strict_types=1);

namespace Src\Sales\Domain\Ports;

use DomainException;

final class InvalidCommissionCalculationException extends DomainException
{
    public static function invalidRate(float $rate): self
    {
        return new self("Commission rate must be between 0 and 100, got {$rate}");
    }

    public static function withMessage(string $message): self
    {
        return new self($message);
    }
}
