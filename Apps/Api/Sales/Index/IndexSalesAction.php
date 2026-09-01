<?php

declare(strict_types=1);

namespace Apps\Api\Sales\Index;

use Src\Sales\Application\Queries\Index\ListSalesQuery;
use Src\Sales\Domain\ReadModels\SaleListItemRM;
use Src\Shared\Framework\Application\Queries\PaginatedCollection;
use Src\Shared\Framework\Infrastructure\Bus\QueryBus\QueryBusInterface;

final readonly class IndexSalesAction
{
    public function __construct(
        private QueryBusInterface $queryBus,
    ) {
    }

    /** @return PaginatedCollection<SaleListItemRM> */
    public function __invoke(IndexSalesDto $dto): PaginatedCollection
    {
        /** @var PaginatedCollection<SaleListItemRM> $result */
        $result = $this->queryBus->query(new ListSalesQuery(
            customerId: $dto->customerId,
            status:     $dto->status,
            dateFrom:   $dto->dateFrom,
            dateTo:     $dto->dateTo,
            limit:      $dto->limit,
            offset:     $dto->offset,
        ));

        return $result;
    }
}
