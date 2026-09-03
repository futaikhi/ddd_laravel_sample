<?php

declare(strict_types=1);

namespace Src\Sales\Application\EventHandlers;

use Src\Sales\Domain\Enums\OrderStatus;
use Src\Sales\Domain\Events\SaleConfirmedEvent;
use Src\Sales\Domain\Repositories\SaleReadModelRepositoryInterface;

final readonly class ProjectSaleListItemOnSaleConfirmedHandler
{
    public function __construct(
        private SaleReadModelRepositoryInterface $readModels,
    ) {
    }

    public function handle(SaleConfirmedEvent $event): void
    {
        $this->readModels->updateSaleListItemStatus(
            saleId: $event->saleId,
            status: OrderStatus::CONFIRMED->value,
            confirmedAt: $event->confirmedAt !== '' ? $event->confirmedAt : null,
        );
    }
}
