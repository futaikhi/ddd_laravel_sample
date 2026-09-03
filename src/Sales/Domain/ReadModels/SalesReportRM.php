<?php

declare(strict_types=1);

namespace Src\Sales\Domain\ReadModels;

/**
 * Read model for aggregated sales reporting.
 */
final readonly class SalesReportRM
{
    public function __construct(
        public string $date,
        public int $salesCount,
        public int $revenueTotal,
        public string $currency,
    ) {
    }
}
