<?php

declare(strict_types=1);

namespace Tests\Unit\Sales\Handlers;

use PHPUnit\Framework\TestCase;
use Src\Sales\Application\Commands\Complete\CompleteSaleCommand;
use Src\Sales\Application\Commands\Complete\CompleteSaleHandler;
use Src\Sales\Domain\Entities\Sale;
use Src\Sales\Domain\Enums\OrderStatus;
use Src\Sales\Domain\Enums\PaymentMethod;
use Src\Sales\Domain\Ports\CommissionCalculatorInterface;
use Src\Sales\Domain\Repositories\SaleRepositoryInterface;
use Src\Sales\Domain\ValueObjects\Commission;
use Src\Sales\Domain\ValueObjects\CustomerId;
use Src\Sales\Domain\ValueObjects\LineItem;
use Src\Sales\Domain\ValueObjects\Money;
use Src\Sales\Domain\ValueObjects\ProductId;
use Src\Sales\Domain\ValueObjects\SaleId;
use Src\Sales\Infrastructure\Commission\MockCommissionService;
use Src\Shared\Framework\Infrastructure\Bus\EventBus\EventBusInterface;

final class CompleteSaleHandlerTest extends TestCase
{
    public function test_it_completes_confirmed_sale_and_locks_commission(): void
    {
        $sale = $this->makeConfirmedSale();
        $repo = $this->makeRepo($sale);

        $commissionPort = new MockCommissionService();
        $commissionPort->setFixedRate(5.0); // 5% commission

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects($this->once())->method('publishEvents');

        $handler = new CompleteSaleHandler($repo, $commissionPort, $eventBus);
        $handler(new CompleteSaleCommand(id: $sale->getId()));

        $this->assertSame(OrderStatus::COMPLETED, $sale->getStatus());

        $commission = $sale->getCommission();
        $this->assertNotNull($commission);
        $this->assertSame(5.0, $commission->getRate());

        // 60000 * 5% = 3000
        $this->assertSame(3000, $commission->getAmount()->amount);
        $this->assertSame('IDR', $commission->getAmount()->currency);
    }

    public function test_it_delegates_commission_calculation_to_port(): void
    {
        $sale       = $this->makeConfirmedSale();
        $repo       = $this->makeRepo($sale);
        $eventBus   = $this->createMock(EventBusInterface::class);
        $calledWith = null;

        // Anonymous port to verify handler passes the aggregate to the calculator
        $commissionPort = new class ($calledWith) implements CommissionCalculatorInterface {
            public ?Sale $capturedSale = null;
            public function __construct(&$out) { $this->out = &$out; }
            public $out;

            public function calculate(Sale $sale): Commission
            {
                $this->capturedSale = $sale;
                return Commission::fromRate($sale->getTotalAmount(), 7.5, 'test');
            }
        };

        $handler = new CompleteSaleHandler($repo, $commissionPort, $eventBus);
        $handler(new CompleteSaleCommand(id: $sale->getId()));

        $this->assertSame($sale, $commissionPort->capturedSale);
        $this->assertSame(7.5, $sale->getCommission()?->getRate());
    }

    private function makeConfirmedSale(): Sale
    {
        $sale = Sale::create(
            SaleId::random(),
            CustomerId::random(),
            [new LineItem(ProductId::fromString('01H8M6KJ5NQ8XX4P0N2VYJ4K5D'), 2, Money::fromCents(30000, 'IDR'))],
        );
        $sale->confirm(PaymentMethod::CASH, 'TXN-CONFIRMED-1');
        $sale->releaseEvents(); // clear created/confirmed events for a clean assertion window
        return $sale;
    }

    private function makeRepo(Sale $sale): SaleRepositoryInterface
    {
        return new class ($sale) implements SaleRepositoryInterface {
            public function __construct(private Sale $sale) {}

            public function store(Sale $sale): void {}

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
