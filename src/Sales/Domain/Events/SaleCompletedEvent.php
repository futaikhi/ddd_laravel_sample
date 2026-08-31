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
    ) {
        parent::__construct();
    }

    public static function fromEntity(Sale $sale): self
    {
        return new self(
            saleId: $sale->getId()->getValue(),
            completedAt: $sale->getCompletedAt()?->format('Y-m-d H:i:s') ?? '',
        );
    }

    public function getName(): string
    {
        return 'sale.completed';
    }
}
