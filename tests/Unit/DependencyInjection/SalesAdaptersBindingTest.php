<?php

declare(strict_types=1);

namespace Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Src\Sales\Domain\Ports\CommissionCalculatorInterface;
use Src\Sales\Domain\Ports\PaymentGatewayInterface;
use Src\Sales\Infrastructure\Commission\MockCommissionService;
use Src\Sales\Infrastructure\Payment\MockPaymentGatewayAdapter;

class SalesAdaptersBindingTest extends TestCase
{
    public function test_payment_gateway_adapter_can_be_instantiated(): void
    {
        $adapter = new MockPaymentGatewayAdapter();

        $this->assertInstanceOf(PaymentGatewayInterface::class, $adapter);
    }

    public function test_commission_calculator_can_be_instantiated(): void
    {
        $service = new MockCommissionService();

        $this->assertInstanceOf(CommissionCalculatorInterface::class, $service);
    }

    public function test_adapters_are_type_compatible_with_ports(): void
    {
        // Verify that concrete implementations can be used wherever the interface is expected
        $payment = new MockPaymentGatewayAdapter();
        $commission = new MockCommissionService();

        // These would pass to any method expecting the interfaces
        $this->verifyPaymentGatewayPort($payment);
        $this->verifyCommissionCalculatorPort($commission);
    }

    public function test_dependency_injection_pattern_with_manual_instantiation(): void
    {
        // In production, these would be resolved by the container
        // For testing, we manually instantiate them to verify the pattern
        
        $paymentGateway = new MockPaymentGatewayAdapter();
        $commissionCalculator = new MockCommissionService();

        // Both implement their respective interfaces
        $this->assertInstanceOf(PaymentGatewayInterface::class, $paymentGateway);
        $this->assertInstanceOf(CommissionCalculatorInterface::class, $commissionCalculator);
    }

    private function verifyPaymentGatewayPort(PaymentGatewayInterface $gateway): void
    {
        // This method accepts the interface, demonstrating loose coupling
        $this->assertInstanceOf(PaymentGatewayInterface::class, $gateway);
    }

    private function verifyCommissionCalculatorPort(CommissionCalculatorInterface $calculator): void
    {
        // This method accepts the interface, demonstrating loose coupling
        $this->assertInstanceOf(CommissionCalculatorInterface::class, $calculator);
    }
}
