<?php

declare(strict_types=1);

namespace Tests\Unit\Sales\Integration;

use PHPUnit\Framework\TestCase;
use Src\Sales\Domain\Entities\Sale;
use Src\Sales\Domain\Ports\CommissionCalculatorInterface;
use Src\Sales\Domain\Ports\PaymentGatewayInterface;
use Src\Sales\Domain\ValueObjects\CustomerId;
use Src\Sales\Domain\ValueObjects\LineItem;
use Src\Sales\Domain\ValueObjects\Money;
use Src\Sales\Domain\ValueObjects\PaymentRequest;
use Src\Sales\Domain\ValueObjects\ProductId;
use Src\Sales\Domain\ValueObjects\SaleId;
use Src\Sales\Infrastructure\Commission\MockCommissionService;
use Src\Sales\Infrastructure\Payment\MockPaymentGatewayAdapter;

class PortsAndAdaptersIntegrationTest extends TestCase
{
    public function test_payment_gateway_interface_can_be_implemented(): void
    {
        $adapter = new MockPaymentGatewayAdapter();

        $this->assertInstanceOf(PaymentGatewayInterface::class, $adapter);
    }

    public function test_commission_calculator_interface_can_be_implemented(): void
    {
        $service = new MockCommissionService();

        $this->assertInstanceOf(CommissionCalculatorInterface::class, $service);
    }

    public function test_payment_adapter_can_process_payment_from_domain_value_objects(): void
    {
        $adapter = new MockPaymentGatewayAdapter();

        $request = new PaymentRequest('SALE-123', new Money(100000, 'IDR'), 'IDR', 'Test order');
        $result = $adapter->process($request);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(100000, $result->getAmount()->getValue());
    }

    public function test_commission_service_can_calculate_from_domain_aggregate(): void
    {
        $service = new MockCommissionService();

        $saleId = SaleId::random();
        $customerId = CustomerId::random();
        $lineItem = new LineItem(ProductId::random(), 1, new Money(100000, 'IDR'));

        $sale = Sale::create($saleId, $customerId, [$lineItem]);

        $commission = $service->calculate($sale);

        $this->assertEquals(3.0, $commission->getRate());
        $this->assertEquals(3000, $commission->getAmount()->getValue());
    }

    public function test_ports_provide_loose_coupling_between_domain_and_adapters(): void
    {
        // Domain depends on interfaces
        $paymentPort = new MockPaymentGatewayAdapter();
        $commissionPort = new MockCommissionService();

        // Both implement the interfaces
        $this->assertInstanceOf(PaymentGatewayInterface::class, $paymentPort);
        $this->assertInstanceOf(CommissionCalculatorInterface::class, $commissionPort);

        // This demonstrates the Hexagonal Architecture principle:
        // Domain only knows about interfaces (ports), not implementations (adapters)
    }

    public function test_adapter_can_be_swapped_with_different_implementation(): void
    {
        // Create a mock with success
        $adapter1 = new MockPaymentGatewayAdapter();
        $request = new PaymentRequest('SALE-1', new Money(50000, 'IDR'));
        $result1 = $adapter1->process($request);
        $this->assertTrue($result1->isSuccess());

        // Create another mock configured to fail
        $adapter2 = new MockPaymentGatewayAdapter();
        $adapter2->setShouldSucceed(false);
        $result2 = $adapter2->process($request);
        $this->assertFalse($result2->isSuccess());

        // Both implement the same interface but different behavior
        // This is the power of the hexagonal architecture
    }

    public function test_commission_service_can_be_swapped(): void
    {
        $saleId = SaleId::random();
        $customerId = CustomerId::random();
        $lineItem = new LineItem(ProductId::random(), 1, new Money(1000000, 'IDR'));
        $sale = Sale::create($saleId, $customerId, [$lineItem]);

        // Create one service with default rate
        $service1 = new MockCommissionService();
        $commission1 = $service1->calculate($sale);
        $this->assertEquals(3.0, $commission1->getRate());

        // Create another service with different rate
        $service2 = new MockCommissionService();
        $service2->setFixedRate(10.0);
        $commission2 = $service2->calculate($sale);
        $this->assertEquals(10.0, $commission2->getRate());

        // Both implement the same interface but return different results
    }
}
