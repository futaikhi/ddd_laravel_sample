<?php

declare(strict_types=1);

namespace Apps\Api\Product\Create;

use App\Models\Product;
use Apps\Api\Product\Shared\ProductCreatedRes;

final readonly class CreateProductAction
{
    public function __invoke(CreateProductDto $dto): ProductCreatedRes
    {
        $product = Product::query()->create([
            'id' => $dto->id,
            'name' => $dto->name,
            'sku' => $dto->sku,
            'price' => $dto->price,
            'currency' => $dto->currency,
        ]);

        return new ProductCreatedRes(
            id: $product->id,
            name: $product->name,
            sku: $product->sku,
            price: $product->price,
            currency: $product->currency,
        );
    }
}
