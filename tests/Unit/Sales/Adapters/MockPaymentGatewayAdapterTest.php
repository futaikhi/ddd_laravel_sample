<?php

declare(strict_types=1);

namespace Tests\Unit\Sales\Adapters;

use PHPUnit\Framework\TestCase;
use Src\Sales\Domain\Ports\PaymentFailedException;
use Src\Sales\Domain\ValueObjects\Money;
use Src\Sales\Domain\ValueObjects\PaymentRequest;
use Src\Sales\Infrastructure\Payment\MockPaymentGatewayAdapter;

final class MockPaymentGatewayAdapterTest extends TestCase
{
    private MockPaymentGatewayAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adapter = new MockPaymentGatewayAdapter();
    }

    public function test_successful_payment(): void
    {
        $request = new PaymentRequest('SALE-123', Money::fromCents(50000, 'IDR'), 'IDR', 'Test payment');

        $result = $this->adapter->process($request);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('success', $result->getStatus());
        $this->assertEquals(50000, $result->getAmount()->getValue());
        $this->assertStringStartsWith('MOCK-', $result->getTransactionId());
    }

    public function test_failed_payment_when_configured(): void
    {
        $request = new PaymentRequest('SALE-456', Money::fromCents(100000, 'IDR'));
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
        $request1 = new PaymentRequest('SALE-1', Money::fromCents(50000, 'IDR'));
        $request2 = new PaymentRequest('SALE-2', Money::fromCents(75000, 'IDR'));

        $result1 = $this->adapter->process($request1);
        $result2 = $this->adapter->process($request2);

        $this->assertNotEquals($result1->getTransactionId(), $result2->getTransactionId());
    }

    public function test_successful_refund_records_transaction_id(): void
    {
        $this->adapter->refund('TXN-REFUND-001');

        $this->assertSame(['TXN-REFUND-001'], $this->adapter->getRefundedTransactionIds());
    }

    public function test_failed_refund_when_configured(): void
    {
        $this->adapter->setRefundShouldSucceed(false);
        $this->adapter->setFailureMessage('Refund rejected');

        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Refund rejected');

        $this->adapter->refund('TXN-REFUND-002');
    }

    public function test_refund_requires_transaction_id(): void
    {
        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Transaction id is required for refund');

        $this->adapter->refund('');
    }

    public function test_reset_restores_success_state_and_clears_refunds(): void
    {
        $request = new PaymentRequest('SALE-789', Money::fromCents(50000, 'IDR'));

        $this->adapter->setShouldSucceed(false);
        $this->adapter->setRefundShouldSucceed(false);

        $result1 = $this->adapter->process($request);
        $this->assertFalse($result1->isSuccess());

        $this->adapter->setRefundShouldSucceed(true);
        $this->adapter->refund('TXN-RESET-001');
        $this->assertSame(['TXN-RESET-001'], $this->adapter->getRefundedTransactionIds());

        $this->adapter->reset();
        $result2 = $this->adapter->process($request);

        $this->assertTrue($result2->isSuccess());
        $this->assertSame([], $this->adapter->getRefundedTransactionIds());
    }

    public function test_custom_failure_message(): void
    {
        $request = new PaymentRequest('SALE-XYZ', Money::fromCents(50000, 'IDR'));
        $this->adapter->setShouldSucceed(false);
        $this->adapter->setFailureMessage('Card expired');

        $result = $this->adapter->process($request);

        $this->assertEquals('Card expired', $result->getMessage());
    }
}
