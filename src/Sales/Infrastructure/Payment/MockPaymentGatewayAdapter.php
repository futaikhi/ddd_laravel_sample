<?php

declare(strict_types=1);

namespace Src\Sales\Infrastructure\Payment;

use Src\Sales\Domain\Ports\PaymentFailedException;
use Src\Sales\Domain\Ports\PaymentGatewayInterface;
use Src\Sales\Domain\ValueObjects\PaymentRequest;
use Src\Sales\Domain\ValueObjects\PaymentResult;

final class MockPaymentGatewayAdapter implements PaymentGatewayInterface
{
    private bool $shouldSucceed = true;

    private bool $refundShouldSucceed = true;

    private string $failureMessage = 'Payment failed';

    /** @var list<string> */
    private array $refundedTransactionIds = [];

    public function setShouldSucceed(bool $shouldSucceed): void
    {
        $this->shouldSucceed = $shouldSucceed;
    }

    public function setRefundShouldSucceed(bool $refundShouldSucceed): void
    {
        $this->refundShouldSucceed = $refundShouldSucceed;
    }

    public function setFailureMessage(string $message): void
    {
        $this->failureMessage = $message;
    }

    public function process(PaymentRequest $request): PaymentResult
    {
        $transactionId = 'MOCK-' . bin2hex(random_bytes(8));

        if ($this->shouldSucceed) {
            return PaymentResult::success(
                $transactionId,
                $request->getAmount(),
                'Mock payment successful',
            );
        }

        return PaymentResult::failed(
            $transactionId,
            $request->getAmount(),
            $this->failureMessage,
        );
    }

    public function refund(string $transactionId): void
    {
        if ($transactionId === '') {
            throw PaymentFailedException::withMessage('Transaction id is required for refund');
        }

        if (! $this->refundShouldSucceed) {
            throw PaymentFailedException::withMessage($this->failureMessage);
        }

        $this->refundedTransactionIds[] = $transactionId;
    }

    /**
     * @return list<string>
     */
    public function getRefundedTransactionIds(): array
    {
        return $this->refundedTransactionIds;
    }

    public function reset(): void
    {
        $this->shouldSucceed = true;
        $this->refundShouldSucceed = true;
        $this->failureMessage = 'Payment failed';
        $this->refundedTransactionIds = [];
    }
}
