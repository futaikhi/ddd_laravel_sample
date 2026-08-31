<?php

declare(strict_types=1);

namespace Src\Sales\Domain\Events;

use Src\Sales\Domain\Entities\Sale;
use Src\Shared\Framework\Domain\Events\DomainEvent;

final class SaleCreatedEvent extends DomainEvent
{
    public function __construct(
        public readonly string $saleId,
        public readonly string $customerId,
        public readonly int $totalAmount,
    ) {
        parent::__construct();
    }

    public static function fromEntity(Sale $sale): self
    {
        return new self(
            saleId: $sale->getId()->getValue(),
            customerId: $sale->getCustomerId()->getValue(),
            totalAmount: $sale->getTotalAmount()->getValue(),
        );
    }

    public function getName(): string
    {
        return 'sale.created';
    }
}
