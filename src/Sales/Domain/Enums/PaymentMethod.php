<?php

declare(strict_types=1);

namespace Src\Sales\Domain\Enums;

/**
 * Payment methods accepted when confirming a sale.
 *
 * Lives in the Domain layer so both the aggregate and the payment
 * gateway port speak the same ubiquitous language.
 */
enum PaymentMethod: string
{
    case CREDIT_CARD   = 'credit_card';
    case BANK_TRANSFER = 'bank_transfer';
    case E_WALLET      = 'e_wallet';
    case CASH          = 'cash';

    /**
     * Try to build a PaymentMethod from an arbitrary string.
     * Throws \InvalidArgumentException with a helpful message on unknown values.
     */
    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));
        $case = self::tryFrom($normalized);
        if ($case === null) {
            $allowed = implode(', ', array_map(static fn (self $c): string => $c->value, self::cases()));
            throw new \InvalidArgumentException(
                "Invalid payment method '{$value}'. Allowed: {$allowed}."
            );
        }
        return $case;
    }
}
