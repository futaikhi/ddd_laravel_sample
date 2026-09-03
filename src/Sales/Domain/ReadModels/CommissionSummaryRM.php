<?php

declare(strict_types=1);

namespace Src\Sales\Domain\ReadModels;

/**
 * Read model for aggregated commission reporting.
 */
final readonly class CommissionSummaryRM
{
    public function __construct(
        public string $date,
        public int $completedSalesCount,
        public int $totalCommission,
        public string $currency,
    ) {
    }
}
