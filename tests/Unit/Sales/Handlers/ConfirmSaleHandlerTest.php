<?php

declare(strict_types=1);

namespace Tests\Unit\Sales\Handlers;

use PHPUnit\Framework\TestCase;
use Src\Sales\Application\Commands\Confirm\ConfirmSaleCommand;
use Src\Sales\Application\Commands\Confirm\ConfirmSaleHandler;
use Src\Sales\Domain\Entities\Sale;
use Src\Sales\Domain\Enums\OrderStatus;
use Src\Sales\Domain\Enums\PaymentMethod;
use Src\Sales\Domain\Events\SaleConfirmedEvent;
use Src\Sales\Domain\Exceptions\SaleCannotBeConfirmedException;
use Src\Sales\Domain\Ports\PaymentFailedException;
use Src\Sales\Domain\Ports\PaymentGatewayInterface;
use Src\Sales\Domain\Repositories\SaleRepositoryInterface;
use Src\Sales\Domain\ValueObjects\CustomerId;
use Src\Sales\Domain\ValueObjects\LineItem;
use Src\Sales\Domain\ValueObjects\Money;
use Src\Sales\Domain\ValueObjects\PaymentRequest;
use Src\Sales\Domain\ValueObjects\PaymentResult;
use Src\Sales\Domain\ValueObjects\ProductId;
use Src\Sales\Domain\ValueObjects\SaleId;
use Src\Sales\Domain\ValueObjects\SalesFilter;
use Src\Sales\Infrastructure\Payment\MockPaymentGatewayAdapter;

final class ConfirmSaleHandlerTest extends TestCase
{
    public function test_it_confirms_sale_when_payment_succeeds(): void
    {
        $sale = $this->makeSale();
        $repo = $this->makeRepo($sale);
        $paymentPort = new MockPaymentGatewayAdapter();

        $handler = new ConfirmSaleHandler($repo, $paymentPort);
        $handler(new ConfirmSaleCommand(
            id: $sale->getId(),
            paymentMethod: PaymentMethod::CREDIT_CARD,
        ));

        $this->assertSame(OrderStatus::CONFIRMED, $sale->getStatus());
        $this->assertSame(PaymentMethod::CREDIT_CARD, $sale->getPaymentMethod());
        $this->assertNotNull($sale->getTransactionId());
        $this->assertStringStartsWith('MOCK-', $sale->getTransactionId() ?? '');
        $this->assertTrue($repo->stored);
    }

    public function test_it_keeps_confirmed_event_recorded_for_repository_publishing(): void
    {
        $sale = $this->makeSale();
        $repo = $this->makeRepo($sale);
        $paymentPort = new MockPaymentGatewayAdapter();

        $handler = new ConfirmSaleHandler($repo, $paymentPort);
        $handler(new ConfirmSaleCommand(
            id: $sale->getId(),
            paymentMethod: PaymentMethod::CREDIT_CARD,
        ));

        $events = $sale->pullDomainEvents();

        $this->assertNotEmpty($events);
        $this->assertContainsOnlyInstancesOf(SaleConfirmedEvent::class, $events);
        $this->assertSame($sale->getId()->getValue(), $events[0]->saleId);
        $this->assertNotEmpty($events[0]->confirmedAt);
    }

    public function test_it_throws_and_keeps_sale_pending_when_payment_fails(): void
    {
        $sale = $this->makeSale();
        $repo = $this->makeRepo($sale, expectStore: false);
        $paymentPort = new MockPaymentGatewayAdapter();
        $paymentPort->setShouldSucceed(false);
        $paymentPort->setFailureMessage('Insufficient funds');

        $handler = new ConfirmSaleHandler($repo, $paymentPort);

        try {
            $handler(new ConfirmSaleCommand(
                id: $sale->getId(),
                paymentMethod: PaymentMethod::CREDIT_CARD,
            ));
            $this->fail('Expected PaymentFailedException');
        } catch (PaymentFailedException $e) {
            $this->assertSame('Insufficient funds', $e->getMessage());
        }

        $this->assertSame(OrderStatus::PENDING, $sale->getStatus());
        $this->assertNull($sale->getTransactionId());
        $this->assertNull($sale->getPaymentMethod());
        $this->assertFalse($repo->stored);
    }

    public function test_it_uses_payment_result_transaction_id_on_sale(): void
    {
        $sale = $this->makeSale();
        $repo = $this->makeRepo($sale);

        $paymentPort = new class implements PaymentGatewayInterface {
            public function process(PaymentRequest $request): PaymentResult
            {
                return PaymentResult::success('TXN-FIXED-999', $request->getAmount(), 'ok');
            }

            public function refund(string $transactionId): void
            {
            }
        };

        $handler = new ConfirmSaleHandler($repo, $paymentPort);
        $handler(new ConfirmSaleCommand(
            id: $sale->getId(),
            paymentMethod: PaymentMethod::BANK_TRANSFER,
        ));

        $this->assertSame('TXN-FIXED-999', $sale->getTransactionId());
        $this->assertSame(PaymentMethod::BANK_TRANSFER, $sale->getPaymentMethod());
        $this->assertTrue($repo->stored);
    }

    public function test_it_does_not_call_payment_gateway_when_sale_is_not_pending(): void
    {
        $sale = $this->makeSale();
        $sale->confirm(PaymentMethod::CASH, 'TXN-ALREADY-CONFIRMED');

        $repo = $this->makeRepo($sale, expectStore: false);

        $paymentGateway = $this->createMock(PaymentGatewayInterface::class);
        $paymentGateway->expects($this->never())->method('process');
        $paymentGateway->expects($this->never())->method('refund');

        $handler = new ConfirmSaleHandler($repo, $paymentGateway);

        $this->expectException(SaleCannotBeConfirmedException::class);

        $handler(new ConfirmSaleCommand(
            id: $sale->getId(),
            paymentMethod: PaymentMethod::CREDIT_CARD,
        ));
    }

    private function makeSale(): Sale
    {
        $sale = Sale::create(
            SaleId::random(),
            CustomerId::random(),
            [new LineItem(ProductId::fromString('01H8M6KJ5NQ8XX4P0N2VYJ4K5D'), 2, Money::fromCents(30000, 'IDR'))],
        );

        $sale->releaseEvents();

        return $sale;
    }

    private function makeRepo(Sale $sale, bool $expectStore = true): SaleRepositoryInterface
    {
        return new class($sale, $expectStore) implements SaleRepositoryInterface {
            public bool $stored = false;

            public function __construct(private Sale $sale, private bool $expectStore)
            {
            }

            public function store(Sale $sale): void
            {
                if (! $this->expectStore) {
                    TestCase::fail('Repository store should not be called.');
                }

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

            /** @return list<Sale> */
            public function list(SalesFilter $filter): array
            {
                return [$this->sale];
            }
        };
    }
}
