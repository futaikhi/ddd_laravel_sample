<?php

declare(strict_types=1);

namespace Src\Sales\Domain\Repositories;

use Src\Sales\Domain\Entities\Sale;
use Src\Sales\Domain\ValueObjects\SaleId;

interface SaleRepositoryInterface
{
    public function store(Sale $sale): void;

    public function findById(SaleId $id): ?Sale;

    public function getById(SaleId $id): Sale;
}
