<?php

declare(strict_types=1);

namespace Src\Sales\Domain\Events;

use Src\Sales\Domain\Entities\Sale;
use Src\Shared\Framework\Domain\Events\DomainEvent;

final class SaleConfirmedEvent extends DomainEvent
{
    public function __construct(
        public readonly string $saleId,
        public readonly string $confirmedAt,
    ) {
        parent::__construct();
    }

    public static function fromEntity(Sale $sale): self
    {
        return new self(
            saleId: $sale->getId()->getValue(),
            confirmedAt: $sale->getConfirmedAt()?->format('Y-m-d H:i:s') ?? '',
        );
    }

    public function getName(): string
    {
        return 'sale.confirmed';
    }
}
