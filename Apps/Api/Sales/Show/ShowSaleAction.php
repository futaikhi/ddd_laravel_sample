<?php

declare(strict_types=1);

namespace Apps\Api\Sales\Show;

use Src\Sales\Application\Queries\GetById\GetSaleByIdQuery;
use Src\Sales\Domain\ReadModels\SaleDetailRM;
use Src\Shared\Framework\Infrastructure\Bus\QueryBus\QueryBusInterface;

final readonly class ShowSaleAction
{
    public function __construct(
        private QueryBusInterface $queryBus,
    ) {
    }

    public function __invoke(ShowSaleDto $dto): SaleDetailRM
    {
        /** @var SaleDetailRM $result */
        $result = $this->queryBus->query(new GetSaleByIdQuery(
            saleId: $dto->saleId,
        ));

        return $result;
    }
}
