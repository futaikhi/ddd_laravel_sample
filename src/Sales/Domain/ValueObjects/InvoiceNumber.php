<?php

declare(strict_types=1);

namespace Src\Sales\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class InvoiceNumber
{
    /**
     * Format examples:
     *  - INV-20260910-0001
     *  - INV-2026-0001
     */
    private const PATTERN = '/^[A-Z0-9][A-Z0-9\-]{2,31}$/';

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new InvalidArgumentException('Invoice number cannot be empty.');
        }

        if (strlen($trimmed) > 32) {
            throw new InvalidArgumentException('Invoice number cannot exceed 32 characters.');
        }

        if (preg_match(self::PATTERN, $trimmed) !== 1) {
            throw new InvalidArgumentException(
                'Invoice number must contain only uppercase letters, digits, and dashes.'
            );
        }

        return new self($trimmed);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
