<?php

declare(strict_types=1);

namespace Apps\Api\Sales\Shared;

use Apps\Shared\Http\BaseRes;

final readonly class SaleCreatedRes extends BaseRes
{
    public function __construct(
        public string $id,
        public string $message = 'Sale created successfully',
    ) {
    }
}
