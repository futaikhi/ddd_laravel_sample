<?php

declare(strict_types=1);

namespace Tests\Unit\Sales\Handlers;

use PHPUnit\Framework\TestCase;
use Src\Sales\Application\Queries\Index\ListSalesHandler;
use Src\Sales\Application\Queries\Index\ListSalesQuery;
use Src\Sales\Domain\ReadModels\SaleListItemRM;
use Src\Sales\Domain\Repositories\SaleReadModelRepositoryInterface;
use Src\Sales\Domain\ValueObjects\CustomerId;
use Src\Shared\Framework\Application\Queries\PaginatedCollection;

/**
 * Unit tests for {@see ListSalesHandler} (AC-003 / CQRS query side).
 *
 * Verifies the handler is a thin pass-through that:
 *   - forwards every query filter (customerId, status, dateFrom, dateTo,
 *     limit, offset) to the read-model repository unchanged
 *   - returns the PaginatedCollection<SaleListItemRM> without mutation
 *   - works with all-null filters (no filters supplied)
 *
 * No write-side collaborator is injected, satisfying the AC-003 requirement
 * that read paths must not depend on the write path.
 */
final class ListSalesHandlerTest extends TestCase
{
    public function test_it_forwards_all_filters_to_read_repository_and_returns_paginated_collection(): void
    {
        $customerId = CustomerId::random();

        $items = [
            new SaleListItemRM(
                id: '01H8M6KJ5NQ8XX4P0N2VYJ4K5A',
                customerId: $customerId->getValue(),
                status: 'CONFIRMED',
                totalAmount: 30000,
                currency: 'IDR',
                createdAt: '2026-09-01 10:00:00',
            ),
            new SaleListItemRM(
                id: '01H8M6KJ5NQ8XX4P0N2VYJ4K5B',
                customerId: $customerId->getValue(),
                status: 'COMPLETED',
                totalAmount: 45000,
                currency: 'IDR',
                createdAt: '2026-09-02 11:00:00',
            ),
        ];

        $expected = new PaginatedCollection(
            items: $items,
            pageSize: 20,
            page: 1,
            totalCount: 2,
        );

        $repo = $this->createMock(SaleReadModelRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('paginate')
            ->with(
                $this->identicalTo($customerId),
                $this->identicalTo('CONFIRMED'),
                $this->identicalTo('2026-09-01'),
                $this->identicalTo('2026-09-03'),
                $this->identicalTo(20),
                $this->identicalTo(0),
            )
            ->willReturn($expected);

        $handler = new ListSalesHandler($repo);

        $result = $handler(new ListSalesQuery(
            customerId: $customerId,
            status: 'CONFIRMED',
            dateFrom: '2026-09-01',
            dateTo: '2026-09-03',
            limit: 20,
            offset: 0,
        ));

        $this->assertSame($expected, $result);
        $this->assertCount(2, $result->items);
        $this->assertSame(2, $result->totalCount);
        $this->assertSame(1, $result->page);
        $this->assertSame(20, $result->pageSize);
    }

    public function test_it_supports_query_with_all_null_filters_and_default_pagination(): void
    {
        $expected = new PaginatedCollection(
            items: [],
            pageSize: 20,
            page: 1,
            totalCount: 0,
        );

        $repo = $this->createMock(SaleReadModelRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('paginate')
            ->with(
                $this->isNull(),
                $this->isNull(),
                $this->isNull(),
                $this->isNull(),
                $this->identicalTo(20),
                $this->identicalTo(0),
            )
            ->willReturn($expected);

        $handler = new ListSalesHandler($repo);

        $result = $handler(new ListSalesQuery());

        $this->assertSame($expected, $result);
        $this->assertSame([], $result->items);
        $this->assertSame(0, $result->totalCount);
    }

    public function test_it_forwards_custom_pagination_offset_and_limit(): void
    {
        $expected = new PaginatedCollection(
            items: [],
            pageSize: 50,
            page: 3,
            totalCount: 120,
        );

        $repo = $this->createMock(SaleReadModelRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('paginate')
            ->with(
                $this->isNull(),
                $this->identicalTo('PENDING'),
                $this->isNull(),
                $this->isNull(),
                $this->identicalTo(50),
                $this->identicalTo(100),
            )
            ->willReturn($expected);

        $handler = new ListSalesHandler($repo);

        $result = $handler(new ListSalesQuery(
            status: 'PENDING',
            limit: 50,
            offset: 100,
        ));

        $this->assertSame($expected, $result);
        $this->assertSame(3, $result->page);
    }
}
