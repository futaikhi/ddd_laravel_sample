<?php

declare(strict_types=1);

namespace Tests\Unit\Sales\Adapters;

use PHPUnit\Framework\TestCase;
use Src\Sales\Domain\ValueObjects\Money;
use Src\Sales\Domain\ValueObjects\PaymentRequest;
use Src\Sales\Domain\ValueObjects\PaymentResult;
use Src\Sales\Infrastructure\Payment\MockPaymentGatewayAdapter;

class MockPaymentGatewayAdapterTest extends TestCase
{
    private MockPaymentGatewayAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new MockPaymentGatewayAdapter();
    }

    public function test_successful_payment(): void
    {
        $request = new PaymentRequest('SALE-123', new Money(50000, 'IDR'), 'IDR', 'Test payment');

        $result = $this->adapter->process($request);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('success', $result->getStatus());
        $this->assertEquals(50000, $result->getAmount()->getValue());
        $this->assertStringStartsWith('MOCK-', $result->getTransactionId());
    }

    public function test_failed_payment_when_configured(): void
    {
        $request = new PaymentRequest('SALE-456', new Money(100000, 'IDR'));
        $this->adapter->setShouldSucceed(false);
        $this->adapter->setFailureMessage('Insufficient funds');

        $result = $this->adapter->process($request);

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('failed', $result->getStatus());
        $this->assertEquals('Insufficient funds', $result->getMessage());
        $this->assertEquals(100000, $result->getAmount()->getValue());
    }

    public function test_multiple_transactions_have_different_ids(): void
    {
        $request1 = new PaymentRequest('SALE-1', new Money(50000, 'IDR'));
        $request2 = new PaymentRequest('SALE-2', new Money(75000, 'IDR'));

        $result1 = $this->adapter->process($request1);
        $result2 = $this->adapter->process($request2);

        $this->assertNotEquals($result1->getTransactionId(), $result2->getTransactionId());
    }

    public function test_reset_restores_success_state(): void
    {
        $request = new PaymentRequest('SALE-789', new Money(50000, 'IDR'));

        $this->adapter->setShouldSucceed(false);
        $result1 = $this->adapter->process($request);
        $this->assertFalse($result1->isSuccess());

        $this->adapter->reset();
        $result2 = $this->adapter->process($request);
        $this->assertTrue($result2->isSuccess());
    }

    public function test_custom_failure_message(): void
    {
        $request = new PaymentRequest('SALE-XYZ', new Money(50000, 'IDR'));
        $this->adapter->setShouldSucceed(false);
        $this->adapter->setFailureMessage('Card expired');

        $result = $this->adapter->process($request);

        $this->assertEquals('Card expired', $result->getMessage());
    }
}
