<?php

declare(strict_types=1);

namespace Apps\Api\Sales\Index;

use Src\Sales\Domain\ValueObjects\CustomerId;

final readonly class IndexSalesDto
{
    public function __construct(
        public ?CustomerId $customerId = null,
        public ?string $status = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public int $limit = 20,
        public int $offset = 0,
    ) {
    }
}
