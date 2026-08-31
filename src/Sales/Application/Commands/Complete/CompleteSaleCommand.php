<?php

declare(strict_types=1);

namespace Src\Sales\Application\Commands\Complete;

use Src\Sales\Domain\ValueObjects\SaleId;
use Src\Shared\Framework\Application\Commands\CommandInterface;

final readonly class CompleteSaleCommand implements CommandInterface
{
    public function __construct(
        public SaleId $id,
    ) {
    }
}
