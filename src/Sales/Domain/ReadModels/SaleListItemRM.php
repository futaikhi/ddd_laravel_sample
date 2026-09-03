<?php

declare(strict_types=1);

namespace Src\Sales\Domain\ReadModels;

/**
 * Read model for the sales list view (GET /sales).
 *
 * Denormalized flat row containing only fields required by the list endpoint.
 */
final readonly class SaleListItemRM
{
    public function __construct(
        public string $id,
        public string $customerId,
        public string $status,
        public int $totalAmount,
        public string $currency,
        public string $createdAt,
    ) {
    }
}
