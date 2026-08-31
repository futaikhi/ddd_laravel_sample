<?php

declare(strict_types=1);

namespace Apps\Api\Sales\Confirm;

use Apps\Shared\Http\AbstractFormRequest;
use Src\Sales\Domain\ValueObjects\SaleId;

final class ConfirmSaleRequest extends AbstractFormRequest
{
    public function getDto(): ConfirmSaleDto
    {
        return new ConfirmSaleDto(
            saleId: SaleId::fromString($this->getHelper()->routeString('id')),
        );
    }
}
