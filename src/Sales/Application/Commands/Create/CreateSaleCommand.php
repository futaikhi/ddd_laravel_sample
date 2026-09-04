<?php

declare(strict_types=1);

namespace Src\Sales\Application\Commands\Create;

use Src\Sales\Domain\ValueObjects\AgentId;
use Src\Sales\Domain\ValueObjects\CustomerId;
use Src\Sales\Domain\ValueObjects\SaleId;
use Src\Shared\Framework\Application\Commands\CommandInterface;

final readonly class CreateSaleCommand implements CommandInterface
{
    /**
     * @param list<CreateSaleLineItem> $items
     */
    public function __construct(
        public SaleId $id,
        public CustomerId $customerId,
        public array $items,
        public ?AgentId $agentId = null,
    ) {
    }
}
