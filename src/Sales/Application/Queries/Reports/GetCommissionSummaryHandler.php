<?php

declare(strict_types=1);

namespace Src\Sales\Application\Queries\Reports;

use Src\Sales\Domain\ReadModels\CommissionSummaryRM;
use Src\Sales\Domain\Repositories\SaleReadModelRepositoryInterface;
use Src\Shared\Framework\Infrastructure\Bus\QueryBus\QueryHandlerInterface;

final readonly class GetCommissionSummaryHandler implements QueryHandlerInterface
{
    public function __construct(
        private SaleReadModelRepositoryInterface $repository,
    ) {
    }

    /** @return list<CommissionSummaryRM> */
    public function __invoke(GetCommissionSummaryQuery $query): array
    {
        return $this->repository->commissionSummary(
            dateFrom: $query->dateFrom,
            dateTo:   $query->dateTo,
        );
    }
}
