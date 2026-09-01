<?php

declare(strict_types=1);

namespace Src\Sales\Domain\ValueObjects;

final class Commission
{
    public function __construct(
        private readonly Money $amount,
        private readonly float $rate,
        private readonly string $description = '',
    ) {
        if ($rate < 0 || $rate > 100) {
            throw new \InvalidArgumentException("Commission rate must be between 0 and 100, got {$rate}");
        }
    }

    public static function fromRate(Money $baseAmount, float $rate, string $description = ''): self
    {
        if ($rate < 0 || $rate > 100) {
            throw new \InvalidArgumentException("Commission rate must be between 0 and 100, got {$rate}");
        }

        // Calculate commission: (baseAmount * rate) / 100
        // Since Money stores amounts as integers (cents), we calculate as:
        // commission = (baseAmount * rate) / 100 and round to nearest integer
        $commissionAmount = (int)round($baseAmount->amount * $rate / 100);
        $commission = new Money($commissionAmount, $baseAmount->currency);

        return new self($commission, $rate, $description);
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getRate(): float
    {
        return $this->rate;
    }

    public function getDescription(): string
    {
        return $this->description;
    }
}
