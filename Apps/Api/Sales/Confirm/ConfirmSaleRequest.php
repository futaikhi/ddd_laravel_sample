<?php

declare(strict_types=1);

namespace Apps\Api\Sales\Confirm;

use Apps\Shared\Http\AbstractFormRequest;
use Src\Sales\Domain\Enums\PaymentMethod;
use Src\Sales\Domain\ValueObjects\SaleId;

final class ConfirmSaleRequest extends AbstractFormRequest
{
    public function getDto(): ConfirmSaleDto
    {
        $raw = $this->getHelper()->getString('payment_method');
        if ($raw === '') {
            throw new \InvalidArgumentException('payment_method is required');
        }

        // Whitelist against the PaymentMethod enum. Throws \InvalidArgumentException
        // for unknown values, which is mapped to HTTP 422 by the exception handler.
        $paymentMethod = PaymentMethod::fromString($raw);

        return new ConfirmSaleDto(
            saleId: SaleId::fromString($this->getHelper()->routeString('id')),
            paymentMethod: $paymentMethod,
        );
    }
}
