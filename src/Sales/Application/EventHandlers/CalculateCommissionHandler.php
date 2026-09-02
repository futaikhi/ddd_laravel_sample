<?php

declare(strict_types=1);

namespace Src\Sales\Application\EventHandlers;

use Illuminate\Support\Facades\Log;
use Src\Sales\Domain\Events\CommissionCalculatedEvent;
use Src\Sales\Domain\Events\SaleCompletedEvent;
use Src\Shared\Framework\Infrastructure\Bus\EventBus\EventBusInterface;

/**
 * Converts SaleCompletedEvent commission data into an explicit commission event.
 *
 * The aggregate already locks commission when the sale is completed. This handler
 * publishes CommissionCalculatedEvent so audit/projections can react to the more
 * specific business fact without depending on SaleCompletedEvent semantics.
 */
final readonly class CalculateCommissionHandler
{
    public function __construct(
        private EventBusInterface $eventBus,
    ) {
    }

    public function handle(SaleCompletedEvent $event): void
    {
        $commissionCalculated = CommissionCalculatedEvent::fromSaleCompleted($event);

        Log::info('Commission calculated event published', [
            'sale_id' => $commissionCalculated->saleId,
            'commission_amount' => $commissionCalculated->amount,
            'commission_rate' => $commissionCalculated->percentage,
            'currency' => $commissionCalculated->currency,
        ]);

        $this->eventBus->publishEvents([$commissionCalculated]);
    }
}
