<?php

declare(strict_types=1);

namespace Src\Sales\Domain\ValueObjects;

final class PaymentRequest
{
    public function __construct(
        private readonly string $saleId,
        private readonly Money $amount,
        private readonly string $currency = 'IDR',
        private readonly string $description = '',
    ) {
    }

    public function getSaleId(): string
    {
        return $this->saleId;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getDescription(): string
    {
        return $this->description;
    }
}
