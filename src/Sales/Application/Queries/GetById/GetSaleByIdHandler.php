<?php

declare(strict_types=1);

namespace Src\Sales\Application\Queries\GetById;

use Src\Sales\Domain\Exceptions\SaleNotFoundException;
use Src\Sales\Domain\ReadModels\SaleDetailRM;
use Src\Sales\Domain\Repositories\SaleReadModelRepositoryInterface;
use Src\Shared\Framework\Infrastructure\Bus\QueryBus\QueryHandlerInterface;

final readonly class GetSaleByIdHandler implements QueryHandlerInterface
{
    public function __construct(
        private SaleReadModelRepositoryInterface $repository,
    ) {
    }

    public function __invoke(GetSaleByIdQuery $query): SaleDetailRM
    {
        $detail = $this->repository->findDetail($query->saleId);
        if ($detail === null) {
            throw SaleNotFoundException::withId($query->saleId);
        }
        return $detail;
    }
}
