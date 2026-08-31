<?php

declare(strict_types=1);

namespace Src\Sales\Application\Commands\Cancel;

use Src\Sales\Domain\ValueObjects\SaleId;
use Src\Shared\Framework\Application\Commands\CommandInterface;

final readonly class CancelSaleCommand implements CommandInterface
{
    public function __construct(
        public SaleId $id,
        public string $reason,
    ) {
    }
}
