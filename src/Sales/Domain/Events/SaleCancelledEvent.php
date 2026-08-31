<?php

declare(strict_types=1);

namespace Src\Sales\Domain\Events;

use Src\Sales\Domain\Entities\Sale;
use Src\Shared\Framework\Domain\Events\DomainEvent;

final class SaleCancelledEvent extends DomainEvent
{
    public function __construct(
        public readonly string $saleId,
        public readonly string $reason,
        public readonly string $cancelledAt,
    ) {
        parent::__construct();
    }

    public static function fromEntity(Sale $sale): self
    {
        return new self(
            saleId: $sale->getId()->getValue(),
            reason: $sale->getCancellationReason() ?? '',
            cancelledAt: $sale->getCancelledAt()?->format('Y-m-d H:i:s') ?? '',
        );
    }

    public function getName(): string
    {
        return 'sale.cancelled';
    }
}
