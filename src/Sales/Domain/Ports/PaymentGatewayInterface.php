<?php

declare(strict_types=1);

namespace Src\Sales\Domain\Ports;

use Src\Sales\Domain\ValueObjects\PaymentRequest;
use Src\Sales\Domain\ValueObjects\PaymentResult;

interface PaymentGatewayInterface
{
    /**
     * Process payment request and return payment result.
     *
     * @throws PaymentFailedException
     * @throws PaymentGatewayException
     */
    public function process(PaymentRequest $request): PaymentResult;

    /**
     * Refund captured payment transaction.
     *
     * @throws PaymentFailedException
     * @throws PaymentGatewayException
     */
    public function refund(string $transactionId): void;
}
