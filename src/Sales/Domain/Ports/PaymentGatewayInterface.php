<?php

declare(strict_types=1);

namespace Src\Sales\Domain\Ports;

use Src\Sales\Domain\ValueObjects\PaymentRequest;
use Src\Sales\Domain\ValueObjects\PaymentResult;

interface PaymentGatewayInterface
{
    /**
     * Process a payment request and return the result
     *
     * @throws PaymentFailedException
     * @throws PaymentGatewayException
     */
    public function process(PaymentRequest $request): PaymentResult;
}
