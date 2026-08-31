<?php

declare(strict_types=1);

namespace Src\Sales\Application\Commands\Create;

use Src\Sales\Domain\ValueObjects\CustomerId;
use Src\Sales\Domain\ValueObjects\LineItem;
use Src\Sales\Domain\ValueObjects\SaleId;
use Src\Shared\Framework\Application\Commands\CommandInterface;

final readonly class CreateSaleCommand implements CommandInterface
{
    /**
     * @param list<LineItem> $lineItems
     */
    public function __construct(
        public SaleId $id,
        public CustomerId $customerId,
        public array $lineItems,
    ) {
    }
}
