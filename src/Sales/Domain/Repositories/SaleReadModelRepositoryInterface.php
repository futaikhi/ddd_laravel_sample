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
 * This port is deliberately separate from SaleRepositoryInterface (write side).
 * Query methods return flat read-model DTOs, not domain aggregates.
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

    public function upsertSaleListItem(
        string $saleId,
        string $customerId,
        ?string $customerName,
        string $status,
        int $totalAmount,
        string $currency,
        ?string $createdAt,
    ): void;

    public function updateSaleListItemStatus(
        string $saleId,
        string $status,
        ?string $confirmedAt = null,
        ?string $completedAt = null,
        ?string $cancelledAt = null,
        ?string $cancellationReason = null,
    ): void;

    /**
     * Increment the daily sales report bucket for the given date.
     *
     * Adds `salesCountDelta` to sales_count and `revenueDelta` to revenue_total,
     * creating the row if it does not yet exist.
     */
    public function incrementSalesReport(
        string $reportDate,
        int $salesCountDelta,
        int $revenueDelta,
        string $currency,
    ): void;

    /**
     * Increment the commission summary bucket for the given agent/period.
     *
     * Adds `completedSalesCountDelta` and `totalCommissionDelta` to the row
     * identified by (agentId, periodStart, periodEnd), creating it if needed.
     */
    public function incrementCommissionSummary(
        ?string $agentId,
        string $periodStart,
        string $periodEnd,
        int $completedSalesCountDelta,
        int $totalCommissionDelta,
        string $currency,
    ): void;
}
