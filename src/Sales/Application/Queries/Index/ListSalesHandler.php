<?php

declare(strict_types=1);

namespace Src\Sales\Application\Queries\Index;

use Src\Sales\Domain\ReadModels\SaleListItemRM;
use Src\Sales\Domain\Repositories\SaleReadModelRepositoryInterface;
use Src\Shared\Framework\Application\Queries\PaginatedCollection;
use Src\Shared\Framework\Infrastructure\Bus\QueryBus\QueryHandlerInterface;

final readonly class ListSalesHandler implements QueryHandlerInterface
{
    public function __construct(
        private SaleReadModelRepositoryInterface $repository,
    ) {
    }

    /** @return PaginatedCollection<SaleListItemRM> */
    public function __invoke(ListSalesQuery $query): PaginatedCollection
    {
        return $this->repository->paginate(
            customerId: $query->customerId,
            status:     $query->status,
            dateFrom:   $query->dateFrom,
            dateTo:     $query->dateTo,
            limit:      $query->limit,
            offset:     $query->offset,
        );
    }
}
