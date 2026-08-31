<?php

declare(strict_types=1);

namespace Apps\Api\Customer\Create;

final readonly class CreateCustomerDto
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $email,
        public ?string $phone,
    ) {
    }
}
