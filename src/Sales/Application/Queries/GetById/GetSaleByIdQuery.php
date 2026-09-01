<?php

declare(strict_types=1);

namespace Src\Sales\Application\Queries\GetById;

use Src\Sales\Domain\ValueObjects\SaleId;
use Src\Shared\Framework\Infrastructure\Bus\QueryBus\QueryInterface;

/**
 * @see GetSaleByIdHandler
 */
final readonly class GetSaleByIdQuery implements QueryInterface
{
    public function __construct(
        public SaleId $saleId,
    ) {
    }
}
