<?php

declare(strict_types=1);

namespace Apps\Api\Sales\Reports;

use Apps\Shared\Http\AbstractFormRequest;

final class ReportDateRangeRequest extends AbstractFormRequest
{
    public function getDto(): ReportDateRangeDto
    {
        $h        = $this->getHelper();
        $dateFrom = $h->getStringOrNull('from') ?? '';
        $dateTo   = $h->getStringOrNull('to')   ?? '';

        if ($dateFrom === '' || $dateTo === '') {
            throw new \InvalidArgumentException('"from" and "to" query params are required (YYYY-MM-DD).');
        }

        return new ReportDateRangeDto(
            dateFrom: $dateFrom,
            dateTo:   $dateTo,
        );
    }
}
