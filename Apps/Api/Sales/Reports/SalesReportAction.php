<?php

declare(strict_types=1);

namespace Apps\Api\Sales\Reports;

use Src\Sales\Application\Queries\Reports\GetSalesReportQuery;
use Src\Sales\Domain\ReadModels\SalesReportRM;
use Src\Shared\Framework\Infrastructure\Bus\QueryBus\QueryBusInterface;

final readonly class SalesReportAction
{
    public function __construct(
        private QueryBusInterface $queryBus,
    ) {
    }

    /** @return list<SalesReportRM> */
    public function __invoke(ReportDateRangeDto $dto): array
    {
        /** @var list<SalesReportRM> $result */
        $result = $this->queryBus->query(new GetSalesReportQuery(
            dateFrom: $dto->dateFrom,
            dateTo:   $dto->dateTo,
        ));

        return $result;
    }
}
