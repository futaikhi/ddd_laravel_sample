<?php

declare(strict_types=1);

namespace Apps\Api\Sales\Reports;

use Src\Sales\Application\Queries\Reports\GetCommissionSummaryQuery;
use Src\Sales\Domain\ReadModels\CommissionSummaryRM;
use Src\Shared\Framework\Infrastructure\Bus\QueryBus\QueryBusInterface;

final readonly class CommissionSummaryAction
{
    public function __construct(
        private QueryBusInterface $queryBus,
    ) {
    }

    /** @return list<CommissionSummaryRM> */
    public function __invoke(ReportDateRangeDto $dto): array
    {
        /** @var list<CommissionSummaryRM> $result */
        $result = $this->queryBus->query(new GetCommissionSummaryQuery(
            dateFrom: $dto->dateFrom,
            dateTo:   $dto->dateTo,
        ));

        return $result;
    }
}
