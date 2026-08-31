<?php

declare(strict_types=1);

namespace Apps\Api\Customer\Shared;

use Apps\Shared\Http\BaseRes;

final readonly class CustomerCreatedRes extends BaseRes
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $email,
        public ?string $phone,
    ) {
    }
}
