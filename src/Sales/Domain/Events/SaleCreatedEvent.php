<?php

declare(strict_types=1);

namespace Src\Sales\Domain\Events;

use Src\Sales\Domain\Entities\Sale;
use Src\Shared\Framework\Domain\Events\DomainEvent;

final class SaleCreatedEvent extends DomainEvent
{
    /**
     * @param list<array{productId: string, quantity: int, unitPrice: int, currency: string, total: int}> $items
     */
    public function __construct(
        public readonly string $saleId,
        public readonly string $customerId,
        public readonly int $totalAmount,
        public readonly array $items = [],
        public readonly ?string $createdAt = null,
    ) {
        parent::__construct();
    }

    public static function fromEntity(Sale $sale): self
    {
        return new self(
            saleId: $sale->getId()->getValue(),
            customerId: $sale->getCustomerId()->getValue(),
            totalAmount: $sale->getTotalAmount()->getValue(),
            items: array_map(
                static fn ($lineItem): array => [
                    'productId' => $lineItem->productId->getValue(),
                    'quantity' => $lineItem->quantity,
                    'unitPrice' => $lineItem->unitPrice->getValue(),
                    'currency' => $lineItem->unitPrice->currency,
                    'total' => $lineItem->getTotal()->getValue(),
                ],
                $sale->getLineItems(),
            ),
            createdAt: $sale->getCreatedAt()->format('Y-m-d H:i:s'),
        );
    }

    public function getName(): string
    {
        return 'sale.created';
    }
}
