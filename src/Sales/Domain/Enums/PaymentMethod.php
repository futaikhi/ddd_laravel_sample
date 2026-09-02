<?php

declare(strict_types=1);

namespace Src\Sales\Domain\Enums;

use InvalidArgumentException;

/**
 * Payment methods accepted when confirming sale.
 */
enum PaymentMethod: string
{
    case CREDIT_CARD = 'credit_card';
    case BANK_TRANSFER = 'bank_transfer';
    case E_WALLET = 'e_wallet';
    case CASH = 'cash';

    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));
        $case = self::tryFrom($normalized);

        if ($case === null) {
            $allowed = implode(', ', array_map(static fn (self $case): string => $case->value, self::cases()));

            throw new InvalidArgumentException("Invalid payment method '{$value}'. Allowed: {$allowed}.");
        }

        return $case;
    }
}
