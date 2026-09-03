<?php

declare(strict_types=1);

namespace Src\Sales\Domain\Repositories;

use Src\Sales\Domain\Entities\Sale;
use Src\Sales\Domain\ValueObjects\SaleId;
use Src\Sales\Domain\ValueObjects\SalesFilter;

interface SaleRepositoryInterface
{
    public function store(Sale $sale): void;

    public function findById(SaleId $id): ?Sale;

    public function getById(SaleId $id): Sale;

    /**
     * @return list<Sale>
     */
    public function list(SalesFilter $filter): array;
}
