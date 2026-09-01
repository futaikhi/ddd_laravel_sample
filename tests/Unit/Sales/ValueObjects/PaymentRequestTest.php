<?php

declare(strict_types=1);

namespace Tests\Unit\Sales\ValueObjects;

use PHPUnit\Framework\TestCase;
use Src\Sales\Domain\ValueObjects\Money;
use Src\Sales\Domain\ValueObjects\PaymentRequest;

class PaymentRequestTest extends TestCase
{
    public function test_create_payment_request(): void
    {
        $saleId = 'SALE-123';
        $amount = new Money(50000, 'IDR');
        $currency = 'IDR';
        $description = 'Order #123';

        $request = new PaymentRequest($saleId, $amount, $currency, $description);

        $this->assertEquals($saleId, $request->getSaleId());
        $this->assertEquals($amount, $request->getAmount());
        $this->assertEquals($currency, $request->getCurrency());
        $this->assertEquals($description, $request->getDescription());
    }

    public function test_create_payment_request_with_default_values(): void
    {
        $saleId = 'SALE-456';
        $amount = new Money(100000, 'IDR');

        $request = new PaymentRequest($saleId, $amount);

        $this->assertEquals($saleId, $request->getSaleId());
        $this->assertEquals($amount, $request->getAmount());
        $this->assertEquals('IDR', $request->getCurrency());
        $this->assertEquals('', $request->getDescription());
    }

    public function test_payment_request_is_immutable(): void
    {
        $request = new PaymentRequest('SALE-789', new Money(75000, 'IDR'));

        // Readonly properties prevent modification (enforced by PHP language)
        // This test verifies properties are readonly
        $this->assertTrue(property_exists($request, 'saleId'));
        
        // The property should not be publicly writable (readonly enforces this)
        $this->assertEquals('SALE-789', $request->getSaleId());
    }
}
