<?php

declare(strict_types=1);

namespace Src\Sales\Infrastructure\Payment;

use Illuminate\Support\Facades\Log;
use Src\Sales\Domain\Ports\PaymentFailedException;
use Src\Sales\Domain\Ports\PaymentGatewayException;
use Src\Sales\Domain\Ports\PaymentGatewayInterface;
use Src\Sales\Domain\ValueObjects\PaymentRequest;
use Src\Sales\Domain\ValueObjects\PaymentResult;

final class LaravelPaymentGatewayAdapter implements PaymentGatewayInterface
{
    public function process(PaymentRequest $request): PaymentResult
    {
        try {
            if ($request->getAmount()->getValue() <= 0) {
                throw PaymentFailedException::withMessage('Payment amount must be greater than zero');
            }

            $transactionId = 'LOCAL-' . bin2hex(random_bytes(8));

            Log::info('Local payment processed', [
                'sale_id' => $request->getSaleId(),
                'transaction_id' => $transactionId,
                'amount' => $request->getAmount()->getValue(),
                'currency' => $request->getCurrency(),
                'description' => $request->getDescription(),
            ]);

            return PaymentResult::success(
                $transactionId,
                $request->getAmount(),
                'Local payment successful',
            );
        } catch (PaymentFailedException | PaymentGatewayException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Local payment processing error', [
                'error' => $e->getMessage(),
                'sale_id' => $request->getSaleId(),
            ]);

            throw PaymentGatewayException::withMessage(
                "Payment processing error: {$e->getMessage()}"
            );
        }
    }

    public function refund(string $transactionId): void
    {
        try {
            if ($transactionId === '') {
                throw PaymentFailedException::withMessage('Transaction id is required for refund');
            }

            Log::info('Local payment refund validated', [
                'transaction_id' => $transactionId,
            ]);
        } catch (PaymentFailedException | PaymentGatewayException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Local payment refund error', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId,
            ]);

            throw PaymentGatewayException::withMessage(
                "Payment refund error: {$e->getMessage()}"
            );
        }
    }
}
