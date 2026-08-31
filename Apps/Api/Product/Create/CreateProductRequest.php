<?php

declare(strict_types=1);

namespace Apps\Api\Product\Create;

use Apps\Shared\Http\AbstractFormRequest;
use Illuminate\Support\Str;

final class CreateProductRequest extends AbstractFormRequest
{
    public function getDto(): CreateProductDto
    {
        return new CreateProductDto(
            id: (string) Str::ulid(),
            name: $this->getHelper()->getString('name'),
            sku: $this->getHelper()->getString('sku'),
            price: $this->getHelper()->getInt('price'),
            currency: $this->getHelper()->getString('currency'),
        );
    }
}
