<?php

declare(strict_types=1);

namespace Apps\Api\Sales\Reports;

final readonly class ReportDateRangeDto
{
    public function __construct(
        public string $dateFrom,
        public string $dateTo,
    ) {
    }
}
