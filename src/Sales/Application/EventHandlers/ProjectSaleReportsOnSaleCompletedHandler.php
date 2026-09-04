<?php

declare(strict_types=1);

namespace Src\Sales\Application\EventHandlers;

use Src\Sales\Domain\Enums\OrderStatus;
use Src\Sales\Domain\Events\SaleCompletedEvent;
use Src\Sales\Domain\Repositories\SaleReadModelRepositoryInterface;

/**
 * AC-003 projection for SaleCompletedEvent.
 *
 * Updates three read-model tables on completion:
 *   1. sale_list_items: sets status to COMPLETED and stores completed_at.
 *   2. sales_reports:   increments the daily sales report bucket by 1 sale
 *                       and by the completed sale's total revenue.
 *   3. commission_reports: increments the commission summary bucket for the
 *                          sale's completion date by 1 completed sale and by
 *                          the calculated commission amount.
 */
final readonly class ProjectSaleReportsOnSaleCompletedHandler
{
    public function __construct(
        private SaleReadModelRepositoryInterface $readModels,
    ) {
    }

    public function handle(SaleCompletedEvent $event): void
    {
        $completedAt = $event->completedAt !== '' ? $event->completedAt : null;
        $reportDate = $this->extractDate($event->completedAt);

        $this->readModels->updateSaleListItemStatus(
            saleId: $event->saleId,
            status: OrderStatus::COMPLETED->value,
            completedAt: $completedAt,
        );

        $this->readModels->incrementSalesReport(
            reportDate: $reportDate,
            salesCountDelta: 1,
            revenueDelta: $event->totalAmount,
            currency: $event->commissionCurrency,
        );

        $this->readModels->incrementCommissionSummary(
            agentId: $event->agentId,
            periodStart: $reportDate,
            periodEnd: $reportDate,
            completedSalesCountDelta: 1,
            totalCommissionDelta: $event->commissionAmount,
            currency: $event->commissionCurrency,
        );
    }

    /**
     * Extract a Y-m-d date from a Y-m-d H:i:s timestamp.
     * Falls back to today when the input is empty or malformed.
     */
    private function extractDate(string $timestamp): string
    {
        if ($timestamp === '') {
            return (string) now()->format('Y-m-d');
        }

        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $timestamp);
        if ($parsed instanceof \DateTimeImmutable) {
            return $parsed->format('Y-m-d');
        }

        // Fallback: take the first 10 chars if they look like a date.
        $candidate = substr($timestamp, 0, 10);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $candidate) === 1) {
            return $candidate;
        }

        return (string) now()->format('Y-m-d');
    }
}
