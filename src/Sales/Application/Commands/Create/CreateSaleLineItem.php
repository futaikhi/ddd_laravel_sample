<?php

declare(strict_types=1);

namespace Src\Sales\Application\Commands\Create;

/**
 * Application command input for one sale line item.
 *
 * Keeps HTTP DTOs out of the application layer while allowing the application
 * handler to resolve product price through a hexagonal port.
 */
final readonly class CreateSaleLineItem
{
    public function __construct(
        public string $productId,
        public int $quantity,
    ) {
    }
}
