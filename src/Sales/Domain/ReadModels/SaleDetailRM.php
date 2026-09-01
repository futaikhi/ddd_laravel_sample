<?php

declare(strict_types=1);

namespace Src\Sales\Domain\ReadModels;

/**
 * Read model for a single sale detail (GET /sales/{id}).
 *
 * This is a flat DTO — no domain behavior, no invariants. It carries only
 * the shape the API consumer expects. Cross-domain data (customer name,
 * product name) MAY be joined here on the read side without violating
 * write-side aggregate boundaries.
 */
final readonly class SaleDetailRM
{
    /** @param list<SaleLineItemRM> $lineItems */
    public function __construct(
        public string $id,
        public string $customerId,
        public string $status,
        public int $totalAmount,
        public string $currency,
        public array $lineItems,
        public ?string $paymentMethod,
        public ?string $transactionId,
        public ?int $commissionAmount,
        public ?float $commissionRate,
        public ?string $commissionCurrency,
        public string $createdAt,
        public ?string $confirmedAt,
        public ?string $completedAt,
        public ?string $cancelledAt,
        public ?string $cancellationReason,
    ) {
    }
}
