<?php

declare(strict_types=1);

namespace Apps\Api\Sales\ImportCsv;

/**
 * Represents a single parsed CSV row before domain-level validation.
 */
final readonly class SalesCsvRowDto
{
    public function __construct(
        public int $rowNumber,
        public string $importRef,
        public string $customerId,
        public string $productId,
        public int $quantity,
    ) {
    }
}
