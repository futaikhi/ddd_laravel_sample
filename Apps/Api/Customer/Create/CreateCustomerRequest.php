<?php

declare(strict_types=1);

namespace Apps\Api\Customer\Create;

use Apps\Shared\Http\AbstractFormRequest;
use Illuminate\Support\Str;

final class CreateCustomerRequest extends AbstractFormRequest
{
    public function getDto(): CreateCustomerDto
    {
        return new CreateCustomerDto(
            id: (string) Str::ulid(),
            name: $this->getHelper()->getString('name'),
            email: $this->getHelper()->getStringOrNull('email'),
            phone: $this->getHelper()->getStringOrNull('phone'),
        );
    }
}
