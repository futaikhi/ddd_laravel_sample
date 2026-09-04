<?php

declare(strict_types=1);

namespace Src\Sales\Domain\Ports;

use Src\Sales\Domain\ValueObjects\LineItem;

interface ProductCatalogInterface
{
    /**
     * Build a domain line item using the current catalog price.
     *
     * @throws \Src\Sales\Domain\Exceptions\ProductNotFoundException
     */
    public function lineItemFor(string $productId, int $quantity): LineItem;
}
