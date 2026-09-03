<?php

declare(strict_types=1);

namespace Src\Sales\Application\EventHandlers;

use Src\Sales\Domain\Enums\OrderStatus;
use Src\Sales\Domain\Events\SaleCreatedEvent;
use Src\Sales\Domain\Repositories\SaleReadModelRepositoryInterface;

final readonly class ProjectSaleListItemOnSaleCreatedHandler
{
    public function __construct(
        private SaleReadModelRepositoryInterface $readModels,
    ) {
    }

    public function handle(SaleCreatedEvent $event): void
    {
        $currency = $event->items[0]['currency'] ?? 'IDR';

        $this->readModels->upsertSaleListItem(
            saleId: $event->saleId,
            customerId: $event->customerId,
            customerName: null,
            status: OrderStatus::PENDING->value,
            totalAmount: $event->totalAmount,
            currency: $currency,
            createdAt: $event->createdAt,
        );
    }
}
