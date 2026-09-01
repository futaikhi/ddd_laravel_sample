<?php

declare(strict_types=1);

namespace Apps\Api\Sales\Index;

use Apps\Shared\Http\AbstractFormRequest;
use Src\Sales\Domain\Enums\OrderStatus;
use Src\Sales\Domain\ValueObjects\CustomerId;

final class IndexSalesRequest extends AbstractFormRequest
{
    public function getDto(): IndexSalesDto
    {
        $h = $this->getHelper();

        $customerIdRaw = $h->getStringOrNull('customer_id');
        $customerId    = $customerIdRaw !== null && $customerIdRaw !== ''
            ? CustomerId::fromString($customerIdRaw)
            : null;

        $status = $h->getStringOrNull('status');
        if ($status !== null && $status !== '') {
            // Validate against enum to fail early with a clear error
            OrderStatus::from($status);
        } else {
            $status = null;
        }

        $limit  = $h->getIntOrNull('limit')  ?? 20;
        $offset = $h->getIntOrNull('offset') ?? 0;

        if ($limit < 1) {
            $limit = 20;
        }
        if ($limit > 200) {
            $limit = 200;
        }
        if ($offset < 0) {
            $offset = 0;
        }

        return new IndexSalesDto(
            customerId: $customerId,
            status:     $status,
            dateFrom:   $h->getStringOrNull('date_from'),
            dateTo:     $h->getStringOrNull('date_to'),
            limit:      $limit,
            offset:     $offset,
        );
    }
}
