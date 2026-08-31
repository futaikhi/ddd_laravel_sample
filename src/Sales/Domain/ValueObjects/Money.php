<?php

declare(strict_types=1);

namespace Src\Sales\Domain\ValueObjects;

final class Money
{
    public function __construct(
        public readonly int $amount,
        public readonly string $currency = 'IDR',
    ) {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Money amount cannot be negative');
        }
    }

    public static function fromCents(int $amount, string $currency = 'IDR'): self
    {
        return new self($amount, $currency);
    }

    public static function zero(string $currency = 'IDR'): self
    {
        return new self(0, $currency);
    }

    public function add(self $other): self
    {
        $this->ensureSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function multiply(int $factor): self
    {
        return new self($this->amount * $factor, $this->currency);
    }

    public function getValue(): int
    {
        return $this->amount;
    }

    public function ensureSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException(
                sprintf('Cannot operate on different currencies: %s and %s', $this->currency, $other->currency)
            );
        }
    }
}
