<?php

declare(strict_types=1);

namespace Apps\Api\Sales\Shared;

use Apps\Shared\Http\BaseRes;

final readonly class SaleActionRes extends BaseRes
{
    public function __construct(
        public string $message,
    ) {
    }
}
