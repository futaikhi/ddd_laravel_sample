<?php

declare(strict_types=1);

namespace Src\Sales\Infrastructure\Payment;

use Src\Sales\Domain\Ports\PaymentGatewayInterface;
use Src\Sales\Domain\ValueObjects\PaymentRequest;
use Src\Sales\Domain\ValueObjects\PaymentResult;

final class MockPaymentGatewayAdapter implements PaymentGatewayInterface
{
    private bool $shouldSucceed = true;
    private string $failureMessage = 'Payment failed';

    public function __construct()
    {
    }

    /**
     * Set whether the next payment should succeed or fail
     */
    public function setShouldSucceed(bool $shouldSucceed): void
    {
        $this->shouldSucceed = $shouldSucceed;
    }

    /**
     * Set failure message for failed payments
     */
    public function setFailureMessage(string $message): void
    {
        $this->failureMessage = $message;
    }

    /**
     * Process a payment request (mock implementation)
     */
    public function process(PaymentRequest $request): PaymentResult
    {
        $transactionId = 'MOCK-' . bin2hex(random_bytes(8));

        if ($this->shouldSucceed) {
            return PaymentResult::success(
                $transactionId,
                $request->getAmount(),
                'Mock payment successful'
            );
        }

        return PaymentResult::failed(
            $transactionId,
            $request->getAmount(),
            $this->failureMessage
        );
    }

    /**
     * Reset to default successful state
     */
    public function reset(): void
    {
        $this->shouldSucceed = true;
        $this->failureMessage = 'Payment failed';
    }
}
