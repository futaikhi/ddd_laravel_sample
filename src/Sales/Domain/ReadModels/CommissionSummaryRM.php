<?php

declare(strict_types=1);

namespace Src\Sales\Domain\ReadModels;

/**
 * Read model for the commission summary (GET /sales/reports/commissions).
 *
 * Aggregated per day within the requested period.
 */
final readonly class CommissionSummaryRM
{
    public function __construct(
        public string $date,               // YYYY-MM-DD
        public int $completedSalesCount,   // number of COMPLETED sales
        public int $totalCommission,       // sum of commission_amount in minor units
        public string $currency,
    ) {
    }
}
