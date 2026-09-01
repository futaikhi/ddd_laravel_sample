<?php

declare(strict_types=1);

namespace Src\Sales\Domain\ReadModels;

/**
 * Read model for a sale in the list view (GET /sales).
 *
 * Denormalized/flat: only the fields the list view needs. If later we build
 * a dedicated sale_list_items projection table (see FR-004), this DTO will
 * simply be hydrated from that table instead of from `sales`.
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
