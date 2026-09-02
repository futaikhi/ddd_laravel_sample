<?php

declare(strict_types=1);

namespace Src\Sales\Domain\ValueObjects;

use InvalidArgumentException;

final class PaymentResult
{
    public function __construct(
        private readonly string $transactionId,
        private readonly string $status,
        private readonly Money $amount,
        private readonly string $message = '',
    ) {
        if (! in_array($status, ['success', 'failed', 'pending'], true)) {
            throw new InvalidArgumentException("Invalid payment status: {$status}");
        }
    }

    public static function success(string $transactionId, Money $amount, string $message = ''): self
    {
        return new self($transactionId, 'success', $amount, $message);
    }

    public static function failed(string $transactionId, Money $amount, string $message = ''): self
    {
        return new self($transactionId, 'failed', $amount, $message);
    }

    public static function pending(string $transactionId, Money $amount, string $message = ''): self
    {
        return new self($transactionId, 'pending', $amount, $message);
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
