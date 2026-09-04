<?php

declare(strict_types=1);

namespace Src\Sales\Infrastructure\Product;

use App\Models\Product;
use Src\Sales\Domain\Exceptions\ProductNotFoundException;
use Src\Sales\Domain\Ports\ProductCatalogInterface;
use Src\Sales\Domain\ValueObjects\LineItem;
use Src\Sales\Domain\ValueObjects\Money;
use Src\Sales\Domain\ValueObjects\ProductId;

final class EloquentProductCatalog implements ProductCatalogInterface
{
    public function lineItemFor(string $productId, int $quantity): LineItem
    {
        $product = Product::query()->find($productId);

        if ($product === null) {
            throw ProductNotFoundException::withId($productId);
        }

        return new LineItem(
            productId: ProductId::fromString($productId),
            quantity: $quantity,
            unitPrice: new Money((int) $product->price, (string) $product->currency),
        );
    }
}
