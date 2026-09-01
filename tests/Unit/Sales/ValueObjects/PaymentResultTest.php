<?php

declare(strict_types=1);

namespace Tests\Unit\Sales\ValueObjects;

use PHPUnit\Framework\TestCase;
use Src\Sales\Domain\ValueObjects\Money;
use Src\Sales\Domain\ValueObjects\PaymentResult;

class PaymentResultTest extends TestCase
{
    public function test_create_successful_payment_result(): void
    {
        $amount = new Money(50000, 'IDR');
        $result = PaymentResult::success('TXN-123', $amount, 'Payment successful');

        $this->assertEquals('TXN-123', $result->getTransactionId());
        $this->assertEquals('success', $result->getStatus());
        $this->assertTrue($result->isSuccess());
        $this->assertEquals($amount, $result->getAmount());
        $this->assertEquals('Payment successful', $result->getMessage());
    }

    public function test_create_failed_payment_result(): void
    {
        $amount = new Money(50000, 'IDR');
        $result = PaymentResult::failed('TXN-456', $amount, 'Insufficient funds');

        $this->assertEquals('TXN-456', $result->getTransactionId());
        $this->assertEquals('failed', $result->getStatus());
        $this->assertFalse($result->isSuccess());
        $this->assertEquals($amount, $result->getAmount());
        $this->assertEquals('Insufficient funds', $result->getMessage());
    }

    public function test_create_pending_payment_result(): void
    {
        $amount = new Money(100000, 'IDR');
        $result = PaymentResult::pending('TXN-789', $amount, 'Waiting for confirmation');

        $this->assertEquals('TXN-789', $result->getTransactionId());
        $this->assertEquals('pending', $result->getStatus());
        $this->assertFalse($result->isSuccess());
        $this->assertEquals($amount, $result->getAmount());
    }

    public function test_invalid_payment_status_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid payment status');

        new PaymentResult('TXN-XXX', 'invalid_status', new Money(50000, 'IDR'));
    }

    public function test_payment_result_with_default_message(): void
    {
        $amount = new Money(75000, 'IDR');
        $result = PaymentResult::success('TXN-DEF', $amount);

        $this->assertEquals('', $result->getMessage());
    }
}
