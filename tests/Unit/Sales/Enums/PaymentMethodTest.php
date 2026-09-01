<?php

declare(strict_types=1);

namespace Tests\Unit\Sales\Enums;

use PHPUnit\Framework\TestCase;
use Src\Sales\Domain\Enums\PaymentMethod;

final class PaymentMethodTest extends TestCase
{
    public function test_it_accepts_all_documented_payment_methods(): void
    {
        $this->assertSame(PaymentMethod::CREDIT_CARD,   PaymentMethod::fromString('credit_card'));
        $this->assertSame(PaymentMethod::BANK_TRANSFER, PaymentMethod::fromString('bank_transfer'));
        $this->assertSame(PaymentMethod::E_WALLET,      PaymentMethod::fromString('e_wallet'));
        $this->assertSame(PaymentMethod::CASH,          PaymentMethod::fromString('cash'));
    }

    public function test_it_normalizes_case_and_whitespace(): void
    {
        $this->assertSame(PaymentMethod::CREDIT_CARD, PaymentMethod::fromString('  CREDIT_CARD  '));
        $this->assertSame(PaymentMethod::CASH,        PaymentMethod::fromString('Cash'));
    }

    public function test_it_throws_with_helpful_message_for_unknown_method(): void
    {
        try {
            PaymentMethod::fromString('crypto');
            $this->fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString("Invalid payment method 'crypto'", $e->getMessage());
            $this->assertStringContainsString('credit_card', $e->getMessage());
            $this->assertStringContainsString('bank_transfer', $e->getMessage());
        }
    }
}
