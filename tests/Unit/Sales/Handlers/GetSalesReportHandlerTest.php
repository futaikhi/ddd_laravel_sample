<?php

declare(strict_types=1);

namespace Tests\Unit\Sales\Handlers;

use PHPUnit\Framework\TestCase;
use Src\Sales\Application\Queries\Reports\GetSalesReportHandler;
use Src\Sales\Application\Queries\Reports\GetSalesReportQuery;
use Src\Sales\Domain\ReadModels\SalesReportRM;
use Src\Sales\Domain\Repositories\SaleReadModelRepositoryInterface;

/**
 * Unit tests for {@see GetSalesReportHandler} (AC-003 / CQRS query side).
 *
 * The handler must:
 *   - forward `dateFrom` and `dateTo` to the read-model repository verbatim
 *   - return the read-model list<SalesReportRM> unchanged
 *   - never fabricate aggregation itself (aggregation belongs to the
 *     projection handler {@see ProjectSaleReportsOnSaleCompletedHandler});
 *     the query handler is a pure pass-through.
 */
final class GetSalesReportHandlerTest extends TestCase
{
    public function test_it_returns_report_rows_from_read_repository_for_given_date_range(): void
    {
        $expected = [
            new SalesReportRM(
                date: '2026-09-01',
                salesCount: 3,
                revenueTotal: 90000,
                currency: 'IDR',
            ),
            new SalesReportRM(
                date: '2026-09-02',
                salesCount: 2,
                revenueTotal: 45000,
                currency: 'IDR',
            ),
        ];

        $repo = $this->createMock(SaleReadModelRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('salesReport')
            ->with(
                $this->identicalTo('2026-09-01'),
                $this->identicalTo('2026-09-03'),
            )
            ->willReturn($expected);

        $handler = new GetSalesReportHandler($repo);

        $result = $handler(new GetSalesReportQuery(
            dateFrom: '2026-09-01',
            dateTo: '2026-09-03',
        ));

        $this->assertSame($expected, $result);
        $this->assertCount(2, $result);
        $this->assertSame('2026-09-01', $result[0]->date);
        $this->assertSame(3, $result[0]->salesCount);
        $this->assertSame(90000, $result[0]->revenueTotal);
        $this->assertSame('IDR', $result[0]->currency);
    }

    public function test_it_returns_empty_list_when_read_repository_has_no_rows(): void
    {
        $repo = $this->createMock(SaleReadModelRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('salesReport')
            ->with(
                $this->identicalTo('2026-01-01'),
                $this->identicalTo('2026-01-31'),
            )
            ->willReturn([]);

        $handler = new GetSalesReportHandler($repo);

        $result = $handler(new GetSalesReportQuery(
            dateFrom: '2026-01-01',
            dateTo: '2026-01-31',
        ));

        $this->assertSame([], $result);
    }

    public function test_it_does_not_mutate_repository_response(): void
    {
        $row = new SalesReportRM(
            date: '2026-09-01',
            salesCount: 10,
            revenueTotal: 500000,
            currency: 'IDR',
        );

        $repo = $this->createMock(SaleReadModelRepositoryInterface::class);
        $repo->method('salesReport')->willReturn([$row]);

        $handler = new GetSalesReportHandler($repo);

        $result = $handler(new GetSalesReportQuery(
            dateFrom: '2026-09-01',
            dateTo: '2026-09-01',
        ));

        $this->assertSame($row, $result[0], 'Handler must return the exact same DTO instance from the read repository.');
    }
}
