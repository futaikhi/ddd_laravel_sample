<?php

declare(strict_types=1);

namespace Apps\Api\Sales\Complete;

use Apps\Shared\Http\AbstractFormRequest;
use Src\Sales\Domain\ValueObjects\SaleId;

final class CompleteSaleRequest extends AbstractFormRequest
{
    public function getDto(): CompleteSaleDto
    {
        return new CompleteSaleDto(
            saleId: SaleId::fromString($this->getHelper()->routeString('id')),
        );
    }
}
