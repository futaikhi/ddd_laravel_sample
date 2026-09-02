<?php

declare(strict_types=1);

namespace Src\Sales\Domain\Events;

use Src\Shared\Framework\Domain\Events\DomainEvent;

final class CommissionCalculatedEvent extends DomainEvent
{
    public function __construct(
        public readonly string $saleId,
        public readonly int $amount,
        public readonly float $percentage,
        public readonly string $currency,
        public readonly string $calculatedAt,
    ) {
        parent::__construct();
    }

    public static function fromSaleCompleted(SaleCompletedEvent $event): self
    {
        return new self(
            saleId: $event->saleId,
            amount: $event->commissionAmount,
            percentage: $event->commissionRate,
            currency: $event->commissionCurrency,
            calculatedAt: $event->completedAt,
        );
    }

    public function getName(): string
    {
        return 'commission.calculated';
    }
}
