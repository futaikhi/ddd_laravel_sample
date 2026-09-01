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
 * Pragmatic read adapter for Sales.
 *
 * Queries `sales` / `sale_line_items` directly (no denormalized projection
 * tables yet). When FR-004 introduces event projections, this adapter can
 * be swapped for one that reads from `sale_list_items`, `sales_report`,
 * etc. without touching handlers or queries.
 */
final class SaleReadModelRepository implements SaleReadModelRepositoryInterface
{
    public function findDetail(SaleId $id): ?SaleDetailRM
    {
        $model = SaleModel::with('lineItems')->find($id->getValue());
        if ($model === null) {
            return null;
        }

        $lineItems = [];
        /** @var SaleLineItemModel $item */
        foreach ($model->lineItems as $item) {
            $qty       = (int) $item->quantity;
            $unitPrice = (int) $item->unit_price;
            $lineItems[] = new SaleLineItemRM(
                productId: (string) $item->product_id,
                quantity: $qty,
                unitPrice: $unitPrice,
                currency: (string) $item->currency,
                lineTotal: $qty * $unitPrice,
            );
        }

        return new SaleDetailRM(
            id: (string) $model->id,
            customerId: (string) $model->customer_id,
            status: (string) $model->status,
            totalAmount: (int) $model->total_amount,
            currency: 'IDR',
            lineItems: $lineItems,
            paymentMethod: $model->payment_method !== null ? (string) $model->payment_method : null,
            transactionId: $model->transaction_id !== null ? (string) $model->transaction_id : null,
            commissionAmount: $model->commission_amount !== null ? (int) $model->commission_amount : null,
            commissionRate: $model->commission_rate !== null ? (float) $model->commission_rate : null,
            commissionCurrency: $model->commission_currency !== null ? (string) $model->commission_currency : null,
            createdAt: $this->fmt($model->created_at),
            confirmedAt: $this->fmtNullable($model->confirmed_at),
            completedAt: $this->fmtNullable($model->completed_at),
            cancelledAt: $this->fmtNullable($model->cancelled_at),
            cancellationReason: $model->cancellation_reason !== null ? (string) $model->cancellation_reason : null,
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
        $query = SaleModel::query();

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
            $items[] = new SaleListItemRM(
                id: (string) $row->id,
                customerId: (string) $row->customer_id,
                status: (string) $row->status,
                totalAmount: (int) $row->total_amount,
                currency: 'IDR',
                createdAt: $this->fmt($row->created_at),
            );
        }

        $page = $limit > 0 ? (int) floor($offset / $limit) + 1 : 1;

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
        $rows = DB::table('sales')
            ->selectRaw("DATE(completed_at) as date, COUNT(*) as sales_count, SUM(total_amount) as revenue_total")
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$dateFrom, $dateTo])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            /** @var \stdClass $row */
            $out[] = new SalesReportRM(
                date:         $this->rowString($row, 'date'),
                salesCount:   $this->rowInt($row, 'sales_count'),
                revenueTotal: $this->rowInt($row, 'revenue_total'),
                currency:     'IDR',
            );
        }
        return $out;
    }

    /**
     * @return list<CommissionSummaryRM>
     */
    public function commissionSummary(string $dateFrom, string $dateTo): array
    {
        $rows = DB::table('sales')
            ->selectRaw("DATE(completed_at) as date, COUNT(*) as sales_count, SUM(commission_amount) as commission_total, MAX(commission_currency) as currency")
            ->where('status', 'completed')
            ->whereNotNull('commission_amount')
            ->whereBetween('completed_at', [$dateFrom, $dateTo])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            /** @var \stdClass $row */
            $currency = $this->rowStringOrNull($row, 'currency') ?? 'IDR';
            $out[] = new CommissionSummaryRM(
                date:                $this->rowString($row, 'date'),
                completedSalesCount: $this->rowInt($row, 'sales_count'),
                totalCommission:     $this->rowInt($row, 'commission_total'),
                currency:            $currency,
            );
        }
        return $out;
    }

    private function rowString(\stdClass $row, string $key): string
    {
        $v = $row->{$key} ?? null;
        return is_scalar($v) ? (string) $v : '';
    }

    private function rowStringOrNull(\stdClass $row, string $key): ?string
    {
        $v = $row->{$key} ?? null;
        return is_scalar($v) ? (string) $v : null;
    }

    private function rowInt(\stdClass $row, string $key): int
    {
        $v = $row->{$key} ?? null;
        return is_numeric($v) ? (int) $v : 0;
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
        $s = $this->fmt($value);
        return $s === '' ? null : $s;
    }
}
