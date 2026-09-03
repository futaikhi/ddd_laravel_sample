<?php

declare(strict_types=1);

namespace Src\Sales\Domain\ReadModels;

/**
 * @param list<SaleLineItemRM> $lineItems
 */
final readonly class SaleDetailRM
{
    public function __construct(
        public string $id,
        public string $customerId,
        public string $status,
        public int $totalAmount,
        public string $currency,
        public array $lineItems,
        public ?string $paymentMethod = null,
        public ?string $transactionId = null,
        public ?int $commissionAmount = null,
        public ?float $commissionRate = null,
        public ?string $commissionCurrency = null,
        public ?string $createdAt = null,
        public ?string $confirmedAt = null,
        public ?string $completedAt = null,
        public ?string $cancelledAt = null,
        public ?string $cancellationReason = null,
    ) {
    }
}
