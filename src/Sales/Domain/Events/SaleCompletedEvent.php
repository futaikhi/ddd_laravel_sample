<?php

declare(strict_types=1);

namespace Src\Sales\Domain\Events;

use Src\Sales\Domain\Entities\Sale;
use Src\Shared\Framework\Domain\Events\DomainEvent;

final class SaleCompletedEvent extends DomainEvent
{
    public function __construct(
        public readonly string $saleId,
        public readonly string $completedAt,
        public readonly int $totalAmount,
        public readonly int $commissionAmount,
        public readonly float $commissionRate,
        public readonly string $commissionCurrency,
    ) {
        parent::__construct();
    }

    public static function fromEntity(Sale $sale): self
    {
        $commission = $sale->getCommission();

        return new self(
            saleId: $sale->getId()->getValue(),
            completedAt: $sale->getCompletedAt()?->format('Y-m-d H:i:s') ?? '',
            totalAmount: $sale->getTotalAmount()->getValue(),
            commissionAmount: $commission?->getAmount()->getValue() ?? 0,
            commissionRate: $commission?->getRate() ?? 0.0,
            commissionCurrency: $commission?->getAmount()->currency ?? $sale->getTotalAmount()->currency,
        );
    }

    public function getName(): string
    {
        return 'sale.completed';
    }
}
