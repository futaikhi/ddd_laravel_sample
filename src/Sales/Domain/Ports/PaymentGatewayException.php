<?php

declare(strict_types=1);

namespace Src\Sales\Domain\Ports;

use DomainException;

final class PaymentGatewayException extends DomainException
{
    public static function withMessage(string $message): self
    {
        return new self($message);
    }
}
