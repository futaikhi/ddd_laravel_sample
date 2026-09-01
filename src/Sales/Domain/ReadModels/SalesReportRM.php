<?php

declare(strict_types=1);

namespace Src\Sales\Domain\ReadModels;

/**
 * Read model for the sales report (GET /sales/reports/sales?from=...&to=...).
 *
 * Aggregated per day within the requested period.
 */
final readonly class SalesReportRM
{
    public function __construct(
        public string $date,          // YYYY-MM-DD
        public int $salesCount,       // number of COMPLETED sales that day
        public int $revenueTotal,     // sum of total_amount in minor units
        public string $currency,
    ) {
    }
}
