<?php

declare(strict_types=1);

namespace Src\Sales\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Src\Sales\Domain\ReadModels\CommissionSummaryRM;
use Src\Sales\Domain\ReadModels\SaleDetailRM;
use Src\Sales\Domain\ReadModels\SaleLineItemRM;
use Src\Sales\Domain\ReadModels\SaleListItemRM;
use Src\Sales\Domain\ReadModels\SalesReportRM;
use Src\Sales\Domain\Repositories\SaleReadModelRepositoryInterface;
use Src\Sales\Domain\ValueObjects\CustomerId;
use Src\Sales\Domain\ValueObjects\SaleId;
use Src\Shared\Framework\Application\Queries\PaginatedCollection;

/**
 * Read adapter for Sales CQRS read models.
 */
final class SaleReadModelRepository implements SaleReadModelRepositoryInterface
{
    public function findDetail(SaleId $id): ?SaleDetailRM
    {
        $sale = DB::table('sale_list_items')
            ->where('id', $id->getValue())
            ->first();

        if ($sale === null) {
            return null;
        }

        $lineItems = [];
        $rows = DB::table('sale_line_items')
            ->where('sale_id', $id->getValue())
            ->orderBy('product_id')
            ->get();

        foreach ($rows as $row) {
            /** @var \stdClass $row */
            $quantity = $this->rowInt($row, 'quantity');
            $unitPrice = $this->rowInt($row, 'unit_price');

            $lineItems[] = new SaleLineItemRM(
                productId: $this->rowString($row, 'product_id'),
                quantity: $quantity,
                unitPrice: $unitPrice,
                currency: $this->rowStringOrNull($row, 'currency') ?? $this->rowString($sale, 'currency'),
                lineTotal: $quantity * $unitPrice,
            );
        }

        return new SaleDetailRM(
            id: $this->rowString($sale, 'id'),
            customerId: $this->rowString($sale, 'customer_id'),
            status: $this->rowString($sale, 'status'),
            totalAmount: $this->rowInt($sale, 'total_amount'),
            currency: $this->rowString($sale, 'currency'),
            lineItems: $lineItems,
            createdAt: $this->fmtNullable($sale->created_at ?? null),
            confirmedAt: $this->fmtNullable($sale->confirmed_at ?? null),
            completedAt: $this->fmtNullable($sale->completed_at ?? null),
            cancelledAt: $this->fmtNullable($sale->cancelled_at ?? null),
            cancellationReason: $this->rowStringOrNull($sale, 'cancellation_reason'),
        );
    }

    public function paginate(
        ?CustomerId $customerId,
        ?string $status,
        ?string $dateFrom,
        ?string $dateTo,
        int $limit,
        int $offset,
    ): PaginatedCollection {
        $query = DB::table('sale_list_items');

        if ($customerId !== null) {
            $query->where('customer_id', $customerId->getValue());
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($dateFrom !== null) {
            $query->where('created_at', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->where('created_at', '<=', $dateTo);
        }

        $total = (int) $query->count();
        $rows = $query
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->limit($limit)
            ->offset($offset)
            ->get();

        $items = [];
        foreach ($rows as $row) {
            /** @var \stdClass $row */
            $items[] = new SaleListItemRM(
                id: $this->rowString($row, 'id'),
                customerId: $this->rowString($row, 'customer_id'),
                status: $this->rowString($row, 'status'),
                totalAmount: $this->rowInt($row, 'total_amount'),
                currency: $this->rowString($row, 'currency'),
                createdAt: $this->fmt($row->created_at ?? null),
            );
        }

        $page = $limit > 0 ? ((int) floor($offset / $limit)) + 1 : 1;

        return new PaginatedCollection(
            items: $items,
            pageSize: $limit,
            page: $page,
            totalCount: $total,
        );
    }

    /**
     * @return list<SalesReportRM>
     */
    public function salesReport(string $dateFrom, string $dateTo): array
    {
        $rows = DB::table('sales_reports')
            ->whereBetween('report_date', [$dateFrom, $dateTo])
            ->orderBy('report_date')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            /** @var \stdClass $row */
            $out[] = new SalesReportRM(
                date: $this->rowString($row, 'report_date'),
                salesCount: $this->rowInt($row, 'sales_count'),
                revenueTotal: $this->rowInt($row, 'revenue_total'),
                currency: $this->rowString($row, 'currency'),
            );
        }

        return $out;
    }

    /**
     * @return list<CommissionSummaryRM>
     */
    public function commissionSummary(string $dateFrom, string $dateTo): array
    {
        $rows = DB::table('commission_reports')
            ->where('period_start', '>=', $dateFrom)
            ->where('period_end', '<=', $dateTo)
            ->orderBy('period_start')
            ->orderBy('period_end')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            /** @var \stdClass $row */
            $out[] = new CommissionSummaryRM(
                date: $this->rowString($row, 'period_start'),
                completedSalesCount: $this->rowInt($row, 'completed_sales_count'),
                totalCommission: $this->rowInt($row, 'total_commission'),
                currency: $this->rowString($row, 'currency'),
            );
        }

        return $out;
    }

    public function upsertSaleListItem(
        string $saleId,
        string $customerId,
        ?string $customerName,
        string $status,
        int $totalAmount,
        string $currency,
        ?string $createdAt,
    ): void {
        $now = now();

        DB::table('sale_list_items')->updateOrInsert(
            ['id' => $saleId],
            [
                'customer_id' => $customerId,
                'customer_name' => $customerName,
                'status' => $status,
                'total_amount' => $totalAmount,
                'currency' => $currency,
                'created_at' => $createdAt ?? $now,
                'projected_at' => $now,
            ],
        );
    }

    public function updateSaleListItemStatus(
        string $saleId,
        string $status,
        ?string $confirmedAt = null,
        ?string $completedAt = null,
        ?string $cancelledAt = null,
        ?string $cancellationReason = null,
    ): void {
        $values = [
            'status' => $status,
            'projected_at' => now(),
        ];

        if ($confirmedAt !== null) {
            $values['confirmed_at'] = $confirmedAt;
        }

        if ($completedAt !== null) {
            $values['completed_at'] = $completedAt;
        }

        if ($cancelledAt !== null) {
            $values['cancelled_at'] = $cancelledAt;
        }

        if ($cancellationReason !== null) {
            $values['cancellation_reason'] = $cancellationReason;
        }

        DB::table('sale_list_items')
            ->where('id', $saleId)
            ->update($values);
    }

    public function incrementSalesReport(
        string $reportDate,
        int $salesCountDelta,
        int $revenueDelta,
        string $currency,
    ): void {
        DB::transaction(function () use ($reportDate, $salesCountDelta, $revenueDelta, $currency): void {
            $existing = DB::table('sales_reports')
                ->where('report_date', $reportDate)
                ->lockForUpdate()
                ->first();

            $now = now();

            if ($existing === null) {
                DB::table('sales_reports')->insert([
                    'report_date' => $reportDate,
                    'sales_count' => max(0, $salesCountDelta),
                    'revenue_total' => max(0, $revenueDelta),
                    'currency' => $currency,
                    'projected_at' => $now,
                ]);

                return;
            }

            DB::table('sales_reports')
                ->where('report_date', $reportDate)
                ->update([
                    'sales_count' => (int) $existing->sales_count + $salesCountDelta,
                    'revenue_total' => (int) $existing->revenue_total + $revenueDelta,
                    'currency' => $currency,
                    'projected_at' => $now,
                ]);
        });
    }

    public function incrementCommissionSummary(
        ?string $agentId,
        string $periodStart,
        string $periodEnd,
        int $completedSalesCountDelta,
        int $totalCommissionDelta,
        string $currency,
    ): void {
        DB::transaction(function () use (
            $agentId,
            $periodStart,
            $periodEnd,
            $completedSalesCountDelta,
            $totalCommissionDelta,
            $currency,
        ): void {
            $query = DB::table('commission_reports')
                ->where('period_start', $periodStart)
                ->where('period_end', $periodEnd);

            if ($agentId === null) {
                $query->whereNull('agent_id');
            } else {
                $query->where('agent_id', $agentId);
            }

            $existing = $query->lockForUpdate()->first();
            $now = now();

            if ($existing === null) {
                DB::table('commission_reports')->insert([
                    'agent_id' => $agentId,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'completed_sales_count' => max(0, $completedSalesCountDelta),
                    'total_commission' => max(0, $totalCommissionDelta),
                    'currency' => $currency,
                    'projected_at' => $now,
                ]);

                return;
            }

            $updateQuery = DB::table('commission_reports')
                ->where('period_start', $periodStart)
                ->where('period_end', $periodEnd);

            if ($agentId === null) {
                $updateQuery->whereNull('agent_id');
            } else {
                $updateQuery->where('agent_id', $agentId);
            }

            $updateQuery->update([
                'completed_sales_count' => (int) $existing->completed_sales_count + $completedSalesCountDelta,
                'total_commission' => (int) $existing->total_commission + $totalCommissionDelta,
                'currency' => $currency,
                'projected_at' => $now,
            ]);
        });
    }

    private function rowString(\stdClass $row, string $key): string
    {
        $value = $row->{$key} ?? null;

        return is_scalar($value) ? (string) $value : '';
    }

    private function rowStringOrNull(\stdClass $row, string $key): ?string
    {
        $value = $row->{$key} ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    private function rowInt(\stdClass $row, string $key): int
    {
        $value = $row->{$key} ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }

    private function fmt(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return '';
    }

    private function fmtNullable(mixed $value): ?string
    {
        $value = $this->fmt($value);

        return $value === '' ? null : $value;
    }
}
