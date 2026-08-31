<?php

declare(strict_types=1);

namespace Apps\Api\Sales\Cancel;

use Apps\Shared\Http\AbstractFormRequest;
use Src\Sales\Domain\ValueObjects\SaleId;

final class CancelSaleRequest extends AbstractFormRequest
{
    public function getDto(): CancelSaleDto
    {
        return new CancelSaleDto(
            saleId: SaleId::fromString($this->getHelper()->routeString('id')),
            reason: $this->getHelper()->getStringOrNull('reason') ?? 'cancelled by customer',
        );
    }
}
