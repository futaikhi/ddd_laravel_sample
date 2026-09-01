<?php

declare(strict_types=1);

namespace Src\Sales\Application\Queries\Index;

use Src\Sales\Domain\ReadModels\SaleListItemRM;
use Src\Sales\Domain\ValueObjects\CustomerId;
use Src\Shared\Framework\Application\Queries\PaginatedCollection;
use Src\Shared\Framework\Infrastructure\Bus\QueryBus\QueryInterface;

/**
 * @implements QueryInterface<PaginatedCollection<SaleListItemRM>>
 */
final readonly class ListSalesQuery implements QueryInterface
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
