<?php

declare(strict_types=1);

namespace Apps\Api\Sales\Show;

use Apps\Shared\Http\AbstractFormRequest;
use Src\Sales\Domain\ValueObjects\SaleId;

final class ShowSaleRequest extends AbstractFormRequest
{
    public function getDto(): ShowSaleDto
    {
        return new ShowSaleDto(
            saleId: SaleId::fromString($this->getHelper()->routeString('id')),
        );
    }
}
