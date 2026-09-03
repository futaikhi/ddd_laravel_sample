<?php

declare(strict_types=1);

namespace Tests\Feature\Sales\Projections;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Sales\Domain\Enums\OrderStatus;
use Src\Sales\Domain\Events\SaleCancelledEvent;
use Src\Sales\Domain\Events\SaleCompletedEvent;
use Src\Sales\Domain\Events\SaleConfirmedEvent;
use Src\Sales\Domain\Events\SaleCreatedEvent;
use Tests\TestCase;

/**
 * AC-003 projection tests.
 *
 * These tests prove that domain events dispatched by the command side
 * are propagated (via the registered projection handlers in
 * {@see \App\Providers\EventServiceProvider}) into the denormalized
 * read-model tables (sale_list_items, sales_reports, commission_reports).
 *
 * The tests intentionally exercise the *event -> projection -> read-model*
 * path directly rather than driving a full HTTP flow. This isolates the
 * projection contract from HTTP/domain concerns while still executing the
 * same wiring (Laravel's event dispatcher) that the command handlers use.
 */
final class SalesReadModelProjectionsTest extends TestCase
{
    /** @var array<int, string> */
    private array $trackedSaleIds = [];

    /** @var array<int, string> */
    private array $trackedReportDates = [];

    protected function tearDown(): void
    {
        if (! empty($this->trackedSaleIds)) {
            DB::table('sale_list_items')
                ->whereIn('id', $this->trackedSaleIds)
                ->delete();
        }

        if (! empty($this->trackedReportDates)) {
            DB::table('sales_reports')
                ->whereIn('report_date', $this->trackedReportDates)
                ->delete();

            DB::table('commission_reports')
                ->whereIn('period_start', $this->trackedReportDates)
                ->delete();
        }

        parent::tearDown();
    }

    public function test_sale_created_event_projects_a_pending_sale_list_item(): void
    {
        $saleId = $this->trackSale((string) Str::ulid());
        $customerId = (string) Str::ulid();

        event(new SaleCreatedEvent(
            saleId: $saleId,
            customerId: $customerId,
            totalAmount: 250000,
        ));

        $row = DB::table('sale_list_items')->where('id', $saleId)->first();

        $this->assertNotNull($row, 'sale_list_items row should be projected by SaleCreatedEvent');
        $this->assertSame($customerId, $row->customer_id);
        $this->assertSame(OrderStatus::PENDING->value, $row->status);
        $this->assertSame(250000, (int) $row->total_amount);
        $this->assertSame('IDR', $row->currency);
        $this->assertNotNull($row->projected_at);
    }

    public function test_sale_confirmed_event_updates_sale_list_item_status_to_confirmed(): void
    {
        $saleId = $this->trackSale((string) Str::ulid());
        $customerId = (string) Str::ulid();

        // Seed a projected PENDING row (as if SaleCreatedEvent had fired earlier).
        event(new SaleCreatedEvent(
            saleId: $saleId,
            customerId: $customerId,
            totalAmount: 100000,
        ));

        $confirmedAt = '2026-05-10 09:30:00';
        event(new SaleConfirmedEvent(
            saleId: $saleId,
            confirmedAt: $confirmedAt,
        ));

        $row = DB::table('sale_list_items')->where('id', $saleId)->first();

        $this->assertNotNull($row);
        $this->assertSame(OrderStatus::CONFIRMED->value, $row->status);
        $this->assertNotNull($row->confirmed_at, 'confirmed_at should be populated by SaleConfirmedEvent');
    }

    public function test_sale_completed_event_updates_list_item_and_increments_reports_and_commission(): void
    {
        $saleId = $this->trackSale((string) Str::ulid());
        $customerId = (string) Str::ulid();
        $reportDate = $this->trackReportDate('2026-06-15');
        $completedAt = $reportDate.' 14:45:00';

        // Seed pending list-item row first.
        event(new SaleCreatedEvent(
            saleId: $saleId,
            customerId: $customerId,
            totalAmount: 500000,
        ));

        event(new SaleCompletedEvent(
            saleId: $saleId,
            completedAt: $completedAt,
            totalAmount: 500000,
            commissionAmount: 15000,
            commissionRate: 3.0,
            commissionCurrency: 'IDR',
        ));

        // sale_list_items: status flipped to COMPLETED and completed_at populated.
        $listRow = DB::table('sale_list_items')->where('id', $saleId)->first();
        $this->assertNotNull($listRow);
        $this->assertSame(OrderStatus::COMPLETED->value, $listRow->status);
        $this->assertNotNull($listRow->completed_at);

        // sales_reports: daily bucket exists with +1 sale and +revenue for the completion date.
        $reportRow = DB::table('sales_reports')->where('report_date', $reportDate)->first();
        $this->assertNotNull($reportRow, 'sales_reports row should exist for completion date');
        $this->assertGreaterThanOrEqual(1, (int) $reportRow->sales_count);
        $this->assertGreaterThanOrEqual(500000, (int) $reportRow->revenue_total);
        $this->assertSame('IDR', $reportRow->currency);

        // commission_reports: commission bucket exists with +1 sale and +commission for the completion date.
        $commissionRow = DB::table('commission_reports')
            ->where('period_start', $reportDate)
            ->where('period_end', $reportDate)
            ->whereNull('agent_id')
            ->first();
        $this->assertNotNull($commissionRow, 'commission_reports row should exist for completion date');
        $this->assertGreaterThanOrEqual(1, (int) $commissionRow->completed_sales_count);
        $this->assertGreaterThanOrEqual(15000, (int) $commissionRow->total_commission);
        $this->assertSame('IDR', $commissionRow->currency);
    }

    public function test_sale_completed_event_accumulates_reports_and_commission_on_same_date(): void
    {
        $reportDate = $this->trackReportDate('2026-07-20');
        $completedAt = $reportDate.' 10:00:00';

        // Two completions on the same date -> counts and totals should accumulate.
        for ($i = 0; $i < 2; $i++) {
            $saleId = $this->trackSale((string) Str::ulid());

            event(new SaleCreatedEvent(
                saleId: $saleId,
                customerId: (string) Str::ulid(),
                totalAmount: 200000,
            ));

            event(new SaleCompletedEvent(
                saleId: $saleId,
                completedAt: $completedAt,
                totalAmount: 200000,
                commissionAmount: 6000,
                commissionRate: 3.0,
                commissionCurrency: 'IDR',
            ));
        }

        $reportRow = DB::table('sales_reports')->where('report_date', $reportDate)->first();
        $this->assertNotNull($reportRow);
        $this->assertSame(2, (int) $reportRow->sales_count);
        $this->assertSame(400000, (int) $reportRow->revenue_total);

        $commissionRow = DB::table('commission_reports')
            ->where('period_start', $reportDate)
            ->where('period_end', $reportDate)
            ->whereNull('agent_id')
            ->first();
        $this->assertNotNull($commissionRow);
        $this->assertSame(2, (int) $commissionRow->completed_sales_count);
        $this->assertSame(12000, (int) $commissionRow->total_commission);
    }

    public function test_sale_cancelled_event_marks_list_item_cancelled_without_touching_aggregations(): void
    {
        $saleId = $this->trackSale((string) Str::ulid());
        $customerId = (string) Str::ulid();
        $reportDate = $this->trackReportDate('2026-08-05');
        $cancelledAt = $reportDate.' 08:00:00';

        // Seed pending list-item row first.
        event(new SaleCreatedEvent(
            saleId: $saleId,
            customerId: $customerId,
            totalAmount: 150000,
        ));

        event(new SaleCancelledEvent(
            saleId: $saleId,
            reason: 'customer changed mind',
            cancelledAt: $cancelledAt,
        ));

        $listRow = DB::table('sale_list_items')->where('id', $saleId)->first();
        $this->assertNotNull($listRow);
        $this->assertSame(OrderStatus::CANCELLED->value, $listRow->status);
        $this->assertNotNull($listRow->cancelled_at);
        $this->assertSame('customer changed mind', $listRow->cancellation_reason);

        // Domain rule: only PENDING/CONFIRMED sales can be cancelled, so a
        // cancellation must NEVER mutate the aggregation tables.
        $reportRow = DB::table('sales_reports')->where('report_date', $reportDate)->first();
        $this->assertNull($reportRow, 'sales_reports must not be touched by SaleCancelledEvent');

        $commissionRow = DB::table('commission_reports')
            ->where('period_start', $reportDate)
            ->where('period_end', $reportDate)
            ->first();
        $this->assertNull($commissionRow, 'commission_reports must not be touched by SaleCancelledEvent');
    }

    private function trackSale(string $id): string
    {
        $this->trackedSaleIds[] = $id;

        return $id;
    }

    private function trackReportDate(string $date): string
    {
        $this->trackedReportDates[] = $date;

        return $date;
    }
}
