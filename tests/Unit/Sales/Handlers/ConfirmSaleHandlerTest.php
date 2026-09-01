<?php

declare(strict_types=1);

namespace Tests\Unit\Sales\Handlers;

use PHPUnit\Framework\TestCase;
use Src\Sales\Application\Commands\Confirm\ConfirmSaleCommand;
use Src\Sales\Application\Commands\Confirm\ConfirmSaleHandler;
use Src\Sales\Domain\Entities\Sale;
use Src\Sales\Domain\Enums\OrderStatus;
use Src\Sales\Domain\Enums\PaymentMethod;
use Src\Sales\Domain\Ports\PaymentFailedException;
use Src\Sales\Domain\Ports\PaymentGatewayInterface;
use Src\Sales\Domain\Repositories\SaleRepositoryInterface;
use Src\Sales\Domain\ValueObjects\CustomerId;
use Src\Sales\Domain\ValueObjects\LineItem;
use Src\Sales\Domain\ValueObjects\Money;
use Src\Sales\Domain\ValueObjects\PaymentResult;
use Src\Sales\Domain\ValueObjects\ProductId;
use Src\Sales\Domain\ValueObjects\SaleId;
use Src\Sales\Infrastructure\Payment\MockPaymentGatewayAdapter;
use Src\Shared\Framework\Infrastructure\Bus\EventBus\EventBusInterface;

final class ConfirmSaleHandlerTest extends TestCase
{
    public function test_it_confirms_sale_when_payment_succeeds(): void
    {
        $sale        = $this->makeSale();
        $repo        = $this->makeRepo($sale);
        $paymentPort = new MockPaymentGatewayAdapter(); // succeeds by default
        $eventBus    = $this->createMock(EventBusInterface::class);

        // Assert events are published (created + confirmed events)
        $eventBus->expects($this->once())->method('publishEvents');

        $handler = new ConfirmSaleHandler($repo, $paymentPort, $eventBus);
        $handler(new ConfirmSaleCommand(
            id: $sale->getId(),
            paymentMethod: PaymentMethod::CREDIT_CARD,
        ));

        $this->assertSame(OrderStatus::CONFIRMED, $sale->getStatus());
        $this->assertSame(PaymentMethod::CREDIT_CARD, $sale->getPaymentMethod());
        $this->assertNotNull($sale->getTransactionId());
        $this->assertStringStartsWith('MOCK-', $sale->getTransactionId() ?? '');
    }

    public function test_it_throws_and_keeps_sale_pending_when_payment_fails(): void
    {
        $sale        = $this->makeSale();
        $repo        = $this->makeRepo($sale, expectStore: false);
        $paymentPort = new MockPaymentGatewayAdapter();
        $paymentPort->setShouldSucceed(false);
        $paymentPort->setFailureMessage('Insufficient funds');
        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects($this->never())->method('publishEvents');

        $handler = new ConfirmSaleHandler($repo, $paymentPort, $eventBus);

        try {
            $handler(new ConfirmSaleCommand(
                id: $sale->getId(),
                paymentMethod: PaymentMethod::CREDIT_CARD,
            ));
            $this->fail('Expected PaymentFailedException');
        } catch (PaymentFailedException $e) {
            $this->assertSame('Insufficient funds', $e->getMessage());
        }

        // Sale must remain PENDING after payment failure
        $this->assertSame(OrderStatus::PENDING, $sale->getStatus());
        $this->assertNull($sale->getTransactionId());
        $this->assertNull($sale->getPaymentMethod());
    }

    public function test_it_uses_payment_result_transaction_id_on_sale(): void
    {
        $sale     = $this->makeSale();
        $repo     = $this->makeRepo($sale);
        $eventBus = $this->createMock(EventBusInterface::class);

        // Custom payment gateway that returns a fixed transaction id.
        $paymentPort = new class implements PaymentGatewayInterface {
            public function process(\Src\Sales\Domain\ValueObjects\PaymentRequest $request): PaymentResult
            {
                return PaymentResult::success('TXN-FIXED-999', $request->getAmount(), 'ok');
            }
        };

        $handler = new ConfirmSaleHandler($repo, $paymentPort, $eventBus);
        $handler(new ConfirmSaleCommand(
            id: $sale->getId(),
            paymentMethod: PaymentMethod::BANK_TRANSFER,
        ));

        $this->assertSame('TXN-FIXED-999', $sale->getTransactionId());
        $this->assertSame(PaymentMethod::BANK_TRANSFER, $sale->getPaymentMethod());
    }

    private function makeSale(): Sale
    {
        return Sale::create(
            SaleId::random(),
            CustomerId::random(),
            [new LineItem(ProductId::fromString('01H8M6KJ5NQ8XX4P0N2VYJ4K5D'), 2, Money::fromCents(30000, 'IDR'))],
        );
    }

    private function makeRepo(Sale $sale, bool $expectStore = true): SaleRepositoryInterface
    {
        return new class ($sale, $expectStore) implements SaleRepositoryInterface {
            public bool $stored = false;
            public function __construct(private Sale $sale, private bool $expectStore) {}

            public function store(\Src\Sales\Domain\Entities\Sale $sale): void
            {
                $this->stored = true;
            }

            public function findById(SaleId $id): ?Sale
            {
                return $this->sale;
            }

            public function getById(SaleId $id): Sale
            {
                return $this->sale;
            }
        };
    }
}
