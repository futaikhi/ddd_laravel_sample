<?php

declare(strict_types=1);

namespace Src\Sales\Application\Queries\Reports;

use Src\Sales\Domain\ReadModels\SalesReportRM;
use Src\Shared\Framework\Infrastructure\Bus\QueryBus\QueryInterface;

/**
 * @implements QueryInterface<list<SalesReportRM>>
 */
final readonly class GetSalesReportQuery implements QueryInterface
{
    public function __construct(
        public string $dateFrom, // YYYY-MM-DD
        public string $dateTo,   // YYYY-MM-DD
    ) {
    }
}
