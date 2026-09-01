<?php

declare(strict_types=1);

namespace Src\Sales\Infrastructure\Payment;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Src\Sales\Domain\Ports\PaymentFailedException;
use Src\Sales\Domain\Ports\PaymentGatewayException;
use Src\Sales\Domain\Ports\PaymentGatewayInterface;
use Src\Sales\Domain\ValueObjects\PaymentRequest;
use Src\Sales\Domain\ValueObjects\PaymentResult;

final class LaravelPaymentGatewayAdapter implements PaymentGatewayInterface
{
    private string $apiBaseUrl;
    private string $apiKey;

    public function __construct()
    {
        $url = config('services.payment.url');
        $this->apiBaseUrl = is_string($url) ? $url : 'https://api.payment.example.com';
        
        $key = config('services.payment.key');
        $this->apiKey = is_string($key) ? $key : '';
    }

    /**
     * Process a payment request via external payment gateway
     *
     * @throws PaymentFailedException
     * @throws PaymentGatewayException
     */
    public function process(PaymentRequest $request): PaymentResult
    {
        try {
            Log::info('Processing payment', [
                'sale_id' => $request->getSaleId(),
                'amount' => $request->getAmount()->getValue(),
            ]);

            // Build payment request for external gateway
            $payload = [
                'transaction_id' => $request->getSaleId(),
                'amount' => $request->getAmount()->getValue(),
                'currency' => $request->getCurrency(),
                'description' => $request->getDescription(),
            ];

            // Call external payment gateway
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Accept' => 'application/json',
            ])
                ->timeout(10)
                ->post("{$this->apiBaseUrl}/payments", $payload);

            // Parse response
            if ($response->failed()) {
                $jsonResponse = $response->json();
                $message = $this->extractString($jsonResponse, 'message', 'Payment failed');
                Log::warning('Payment failed', ['response' => $jsonResponse]);

                throw PaymentFailedException::withMessage($message);
            }

            $data = $response->json();

            $transactionId = $this->extractString($data, 'id', $request->getSaleId());
            $message = $this->extractString($data, 'message', 'Payment processed successfully');

            return PaymentResult::success(
                $transactionId,
                $request->getAmount(),
                $message
            );
        } catch (PaymentFailedException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Payment gateway error', [
                'error' => $e->getMessage(),
                'sale_id' => $request->getSaleId(),
            ]);

            throw PaymentGatewayException::withMessage(
                "Payment gateway error: {$e->getMessage()}"
            );
        }
    }

    /**
     * Safely extract a string value from a mixed array response
     */
    private function extractString(mixed $data, string $key, string $default): string
    {
        if (is_array($data) && isset($data[$key]) && is_string($data[$key])) {
            return $data[$key];
        }

        return $default;
    }
}
