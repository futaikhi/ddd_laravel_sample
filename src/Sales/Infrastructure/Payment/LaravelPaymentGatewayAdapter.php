<?php

declare(strict_types=1);

namespace Src\Sales\Infrastructure\Payment;

use Illuminate\Support\Facades\Log;
use Src\Sales\Domain\Ports\PaymentFailedException;
use Src\Sales\Domain\Ports\PaymentGatewayException;
use Src\Sales\Domain\Ports\PaymentGatewayInterface;
use Src\Sales\Domain\ValueObjects\PaymentRequest;
use Src\Sales\Domain\ValueObjects\PaymentResult;

/**
 * Local Laravel payment adapter.
 *
 * This sample project does not integrate with a real third-party payment
 * gateway. The adapter still implements PaymentGatewayInterface to keep the
 * hexagonal boundary explicit, but its responsibility is only to validate
 * the payment request that came from the application flow and return a local
 * transaction id.
 */
final class LaravelPaymentGatewayAdapter implements PaymentGatewayInterface
{
    /**
     * Validate the payment request and return a local success result.
     *
     * @throws PaymentFailedException
     * @throws PaymentGatewayException
     */
    public function process(PaymentRequest $request): PaymentResult
    {
        try {
            $amount = $request->getAmount()->getValue();

            if ($request->getSaleId() === '') {
                throw PaymentGatewayException::withMessage('Payment request sale id is required');
            }

            if ($amount <= 0) {
                throw PaymentFailedException::withMessage('Payment amount must be greater than zero');
            }

            if ($request->getCurrency() === '') {
                throw PaymentFailedException::withMessage('Payment currency is required');
            }

            $transactionId = 'LOCAL-' . $request->getSaleId();

            Log::info('Local payment request validated', [
                'sale_id'        => $request->getSaleId(),
                'amount'         => $amount,
                'currency'       => $request->getCurrency(),
                'transaction_id' => $transactionId,
            ]);

            return PaymentResult::success(
                transactionId: $transactionId,
                amount: $request->getAmount(),
                message: 'Local payment validation successful',
            );
        } catch (PaymentFailedException|PaymentGatewayException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Local payment validation error', [
                'error'   => $e->getMessage(),
                'sale_id' => $request->getSaleId(),
            ]);

            throw PaymentGatewayException::withMessage(
                "Payment validation error: {$e->getMessage()}",
            );
        }
    }
}
