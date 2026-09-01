<?php

declare(strict_types=1);

namespace Src\Sales\Domain\Repositories;

use Src\Sales\Domain\ReadModels\CommissionSummaryRM;
use Src\Sales\Domain\ReadModels\SaleDetailRM;
use Src\Sales\Domain\ReadModels\SaleListItemRM;
use Src\Sales\Domain\ReadModels\SalesReportRM;
use Src\Sales\Domain\ValueObjects\CustomerId;
use Src\Sales\Domain\ValueObjects\SaleId;
use Src\Shared\Framework\Application\Queries\PaginatedCollection;

/**
 * Read-side port for Sales.
 *
 * Deliberately separate from SaleRepositoryInterface (write side).
 * Read methods return flat DTOs, never aggregates, and MAY perform
 * cross-table joins because they do not enforce invariants.
 */
interface SaleReadModelRepositoryInterface
{
    public function findDetail(SaleId $id): ?SaleDetailRM;

    /**
     * @return PaginatedCollection<SaleListItemRM>
     */
    public function paginate(
        ?CustomerId $customerId,
        ?string $status,
        ?string $dateFrom,
        ?string $dateTo,
        int $limit,
        int $offset,
    ): PaginatedCollection;

    /**
     * @return list<SalesReportRM>
     */
    public function salesReport(string $dateFrom, string $dateTo): array;

    /**
     * @return list<CommissionSummaryRM>
     */
    public function commissionSummary(string $dateFrom, string $dateTo): array;
}
