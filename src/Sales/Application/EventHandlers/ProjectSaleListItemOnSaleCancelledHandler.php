<?php

declare(strict_types=1);

namespace Src\Sales\Application\EventHandlers;

use Src\Sales\Domain\Enums\OrderStatus;
use Src\Sales\Domain\Events\SaleCancelledEvent;
use Src\Sales\Domain\Repositories\SaleReadModelRepositoryInterface;

/**
 * AC-003 projection for SaleCancelledEvent.
 *
 * Marks the sale_list_items row as CANCELLED and stores the cancellation
 * timestamp/reason. Aggregated read models (sales_reports, commission_reports)
 * are intentionally NOT touched here because the domain forbids cancelling a
 * completed sale (see Sale::isCancellable()), so no rollback of daily sales or
 * commission totals is ever required.
 */
final readonly class ProjectSaleListItemOnSaleCancelledHandler
{
    public function __construct(
        private SaleReadModelRepositoryInterface $readModels,
    ) {
    }

    public function handle(SaleCancelledEvent $event): void
    {
        $this->readModels->updateSaleListItemStatus(
            saleId: $event->saleId,
            status: OrderStatus::CANCELLED->value,
            cancelledAt: $event->cancelledAt !== '' ? $event->cancelledAt : null,
            cancellationReason: $event->reason !== '' ? $event->reason : null,
        );
    }
}
