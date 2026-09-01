<?php

declare(strict_types=1);

namespace Src\Sales\Application\Queries\Reports;

use Src\Sales\Domain\ReadModels\SalesReportRM;
use Src\Sales\Domain\Repositories\SaleReadModelRepositoryInterface;
use Src\Shared\Framework\Infrastructure\Bus\QueryBus\QueryHandlerInterface;

final readonly class GetSalesReportHandler implements QueryHandlerInterface
{
    public function __construct(
        private SaleReadModelRepositoryInterface $repository,
    ) {
    }

    /** @return list<SalesReportRM> */
    public function __invoke(GetSalesReportQuery $query): array
    {
        return $this->repository->salesReport(
            dateFrom: $query->dateFrom,
            dateTo:   $query->dateTo,
        );
    }
}
