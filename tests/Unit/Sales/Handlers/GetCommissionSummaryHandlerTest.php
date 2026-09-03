<?php

declare(strict_types=1);

namespace Tests\Unit\Sales\Handlers;

use PHPUnit\Framework\TestCase;
use Src\Sales\Application\Queries\Reports\GetCommissionSummaryHandler;
use Src\Sales\Application\Queries\Reports\GetCommissionSummaryQuery;
use Src\Sales\Domain\ReadModels\CommissionSummaryRM;
use Src\Sales\Domain\Repositories\SaleReadModelRepositoryInterface;

/**
 * Unit tests for {@see GetCommissionSummaryHandler} (AC-003 / CQRS query side).
 *
 * The handler must:
 *   - forward `dateFrom` and `dateTo` to the read-model repository verbatim
 *   - return the read-model list<CommissionSummaryRM> unchanged
 *   - never derive commission totals itself (that is done by the
 *     projection handler {@see ProjectSaleReportsOnSaleCompletedHandler}
 *     when SaleCompletedEvent is published).
 */
final class GetCommissionSummaryHandlerTest extends TestCase
{
    public function test_it_returns_commission_rows_from_read_repository_for_given_date_range(): void
    {
        $expected = [
            new CommissionSummaryRM(
                date: '2026-09-01',
                completedSalesCount: 4,
                totalCommission: 12000,
                currency: 'IDR',
            ),
            new CommissionSummaryRM(
                date: '2026-09-02',
                completedSalesCount: 1,
                totalCommission: 3000,
                currency: 'IDR',
            ),
        ];

        $repo = $this->createMock(SaleReadModelRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('commissionSummary')
            ->with(
                $this->identicalTo('2026-09-01'),
                $this->identicalTo('2026-09-03'),
            )
            ->willReturn($expected);

        $handler = new GetCommissionSummaryHandler($repo);

        $result = $handler(new GetCommissionSummaryQuery(
            dateFrom: '2026-09-01',
            dateTo: '2026-09-03',
        ));

        $this->assertSame($expected, $result);
        $this->assertCount(2, $result);
        $this->assertSame('2026-09-01', $result[0]->date);
        $this->assertSame(4, $result[0]->completedSalesCount);
        $this->assertSame(12000, $result[0]->totalCommission);
        $this->assertSame('IDR', $result[0]->currency);
    }

    public function test_it_returns_empty_list_when_read_repository_has_no_rows(): void
    {
        $repo = $this->createMock(SaleReadModelRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('commissionSummary')
            ->with(
                $this->identicalTo('2026-02-01'),
                $this->identicalTo('2026-02-28'),
            )
            ->willReturn([]);

        $handler = new GetCommissionSummaryHandler($repo);

        $result = $handler(new GetCommissionSummaryQuery(
            dateFrom: '2026-02-01',
            dateTo: '2026-02-28',
        ));

        $this->assertSame([], $result);
    }

    public function test_it_does_not_mutate_repository_response(): void
    {
        $row = new CommissionSummaryRM(
            date: '2026-09-01',
            completedSalesCount: 7,
            totalCommission: 21000,
            currency: 'IDR',
        );

        $repo = $this->createMock(SaleReadModelRepositoryInterface::class);
        $repo->method('commissionSummary')->willReturn([$row]);

        $handler = new GetCommissionSummaryHandler($repo);

        $result = $handler(new GetCommissionSummaryQuery(
            dateFrom: '2026-09-01',
            dateTo: '2026-09-01',
        ));

        $this->assertSame($row, $result[0], 'Handler must return the exact same DTO instance from the read repository.');
    }
}
